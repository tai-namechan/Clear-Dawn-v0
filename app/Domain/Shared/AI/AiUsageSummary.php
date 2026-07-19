<?php

namespace App\Domain\Shared\AI;

use App\Domain\Shared\Models\AiUsageLog;
use App\Domain\Shared\Models\AiUsageRequest;
use App\Enums\AiUsageRequestStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class AiUsageSummary
{
    public function __construct(
        private AiUsagePeriodResolver $periods,
        private AiCostCalculator $costs,
        private AiUsageLedger $ledger,
    ) {}

    /**
     * @return array{
     *     period: string,
     *     spent_usd: string,
     *     reserved_usd: string,
     *     limit_usd: string,
     *     remaining_usd: string,
     *     progress_ratio: string,
     *     warning: bool,
     *     at_limit: bool,
     *     expired_count: int,
     *     by_model: list<array{model: string, spent_usd: string}>,
     *     by_feature: list<array{feature: string, count: int, spent_usd: string}>
     * }
     */
    public function forUser(int $userId, ?string $period = null): array
    {
        $period ??= $this->periods->periodFor();
        $monthly = $this->ledger->ensureMonthly($userId, $period);
        $spent = AiMoney::of((string) $monthly->spent_usd);
        $reserved = AiMoney::of((string) $monthly->reserved_usd);
        $limit = $this->costs->monthlyLimit();
        $used = $spent->add($reserved);
        $remaining = $limit->sub($used);
        if ($remaining->isNegative()) {
            $remaining = AiMoney::zero();
        }

        $progress = $used->ratioOf($limit);

        $warningThreshold = (string) config('ai.warnings.usage_ratio', '0.80');
        $warning = $used->meetsOrExceedsRatio($limit, $warningThreshold);
        $atLimit = $used->greaterThanOrEqual($limit);

        $expiredCount = AiUsageRequest::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('period', $period)
            ->where('status', AiUsageRequestStatus::Expired)
            ->count();

        return [
            'period' => $period,
            'spent_usd' => $spent->toString(),
            'reserved_usd' => $reserved->toString(),
            'limit_usd' => $limit->toString(),
            'remaining_usd' => $remaining->toString(),
            'progress_ratio' => $progress,
            'warning' => $warning,
            'at_limit' => $atLimit,
            'expired_count' => $expiredCount,
            'by_model' => $this->spendByModel($userId, $period),
            'by_feature' => $this->spendByFeature($userId, $period),
        ];
    }

    /**
     * bannerForUser() の短TTLキャッシュ版。全 Inertia レスポンスの共有 props として
     * 毎ナビゲーションで呼ばれるため、都度の ledger 参照（期首は SUM 集計 + INSERT）を
     * 避ける。バナー表示は最大 TTL 秒だけ遅れるが、警告バナー用途では許容。
     * null（バナー非表示）もキャッシュ対象になるよう配列に包んで保存する。
     *
     * @return array{warning: bool, at_limit: bool, progress_ratio: string, remaining_usd: string, limit_usd: string, spent_usd: string, reserved_usd: string}|null
     */
    public function bannerForUserCached(int $userId, int $ttlSeconds = 60): ?array
    {
        /** @var array{value: array{warning: bool, at_limit: bool, progress_ratio: string, remaining_usd: string, limit_usd: string, spent_usd: string, reserved_usd: string}|null} $cached */
        $cached = Cache::remember(
            "ai-usage-banner:{$userId}",
            $ttlSeconds,
            fn (): array => ['value' => $this->bannerForUser($userId)],
        );

        return $cached['value'];
    }

    /**
     * Lightweight banner snapshot from monthly 1 row (no breakdown queries).
     *
     * @return array{warning: bool, at_limit: bool, progress_ratio: string, remaining_usd: string, limit_usd: string, spent_usd: string, reserved_usd: string}|null
     */
    public function bannerForUser(int $userId, ?string $period = null): ?array
    {
        $period ??= $this->periods->periodFor();
        $monthly = $this->ledger->ensureMonthly($userId, $period);
        $spent = AiMoney::of((string) $monthly->spent_usd);
        $reserved = AiMoney::of((string) $monthly->reserved_usd);
        $limit = $this->costs->monthlyLimit();
        $used = $spent->add($reserved);
        $progress = $used->ratioOf($limit);
        $warningThreshold = (string) config('ai.warnings.usage_ratio', '0.80');

        if (! $used->meetsOrExceedsRatio($limit, $warningThreshold)) {
            return null;
        }

        $remaining = $limit->sub($used);
        if ($remaining->isNegative()) {
            $remaining = AiMoney::zero();
        }

        return [
            'warning' => true,
            'at_limit' => $used->greaterThanOrEqual($limit),
            'progress_ratio' => $progress,
            'remaining_usd' => $remaining->toString(),
            'limit_usd' => $limit->toString(),
            'spent_usd' => $spent->toString(),
            'reserved_usd' => $reserved->toString(),
        ];
    }

    /**
     * @return list<array{model: string, spent_usd: string}>
     */
    private function spendByModel(int $userId, string $period): array
    {
        [$from, $to] = $this->periods->utcBounds($period);
        $totals = [];

        $legacyRows = AiUsageLog::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->whereNull('usage_request_id')
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->select('model', DB::raw('SUM(estimated_cost_usd) as spent_usd'))
            ->groupBy('model')
            ->toBase()
            ->get();

        foreach ($legacyRows as $row) {
            $data = (array) $row;
            $model = (string) ($data['model'] ?? '');
            $totals[$model] = AiMoney::fromAggregate($data['spent_usd'] ?? 0);
        }

        $requestRows = AiUsageRequest::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('period', $period)
            ->whereIn('status', [
                AiUsageRequestStatus::Settled->value,
                AiUsageRequestStatus::Expired->value,
            ])
            ->select('model', DB::raw('SUM(charged_usd) as spent_usd'))
            ->groupBy('model')
            ->toBase()
            ->get();

        foreach ($requestRows as $row) {
            $data = (array) $row;
            $model = (string) ($data['model'] ?? '');
            $amount = AiMoney::fromAggregate($data['spent_usd'] ?? 0);
            $totals[$model] = isset($totals[$model]) ? $totals[$model]->add($amount) : $amount;
        }

        ksort($totals);

        $result = [];
        foreach ($totals as $model => $amount) {
            $result[] = [
                'model' => $model,
                'spent_usd' => $amount->toString(),
            ];
        }

        return $result;
    }

    /**
     * @return list<array{feature: string, count: int, spent_usd: string}>
     */
    private function spendByFeature(int $userId, string $period): array
    {
        [$from, $to] = $this->periods->utcBounds($period);
        /** @var array<string, array{count: int, spent: AiMoney}> $totals */
        $totals = [];

        $legacyRows = AiUsageLog::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->whereNull('usage_request_id')
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->select('feature', DB::raw('COUNT(*) as aggregate_count'), DB::raw('SUM(estimated_cost_usd) as spent_usd'))
            ->groupBy('feature')
            ->toBase()
            ->get();

        foreach ($legacyRows as $row) {
            $data = (array) $row;
            $feature = (string) ($data['feature'] ?? '');
            $totals[$feature] = [
                'count' => (int) ($data['aggregate_count'] ?? 0),
                'spent' => AiMoney::fromAggregate($data['spent_usd'] ?? 0),
            ];
        }

        $requestRows = AiUsageRequest::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('period', $period)
            ->whereIn('status', [
                AiUsageRequestStatus::Settled->value,
                AiUsageRequestStatus::Expired->value,
            ])
            ->select('feature', DB::raw('COUNT(*) as aggregate_count'), DB::raw('SUM(charged_usd) as spent_usd'))
            ->groupBy('feature')
            ->toBase()
            ->get();

        foreach ($requestRows as $row) {
            $data = (array) $row;
            $feature = (string) ($data['feature'] ?? '');
            $amount = AiMoney::fromAggregate($data['spent_usd'] ?? 0);
            $count = (int) ($data['aggregate_count'] ?? 0);
            if (! isset($totals[$feature])) {
                $totals[$feature] = [
                    'count' => $count,
                    'spent' => $amount,
                ];
            } else {
                $totals[$feature]['count'] += $count;
                $totals[$feature]['spent'] = $totals[$feature]['spent']->add($amount);
            }
        }

        ksort($totals);

        $result = [];
        foreach ($totals as $feature => $data) {
            $result[] = [
                'feature' => $feature,
                'count' => $data['count'],
                'spent_usd' => $data['spent']->toString(),
            ];
        }

        return $result;
    }
}
