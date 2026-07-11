<?php

namespace App\Domain\Shared\AI;

use App\Domain\Shared\Models\AiUsageLog;
use App\Domain\Shared\Models\AiUsageRequest;
use App\Enums\AiUsageRequestStatus;
use Illuminate\Support\Collection;
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

        $warningThreshold = AiMoney::of((string) config('ai.warnings.usage_ratio', '0.80'));
        $warning = AiMoney::of($progress)->greaterThanOrEqual($warningThreshold);
        $atLimit = $used->greaterThanOrEqual($limit);

        [$from, $to] = $this->periods->utcBounds($period);

        /** @var Collection<int, object{model: string, spent_usd: string|float|null}> $byModel */
        $byModel = AiUsageLog::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->select('model', DB::raw('SUM(estimated_cost_usd) as spent_usd'))
            ->groupBy('model')
            ->orderBy('model')
            ->get();

        /** @var Collection<int, object{feature: string, count: int|string, spent_usd: string|float|null}> $byFeature */
        $byFeature = AiUsageLog::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->select('feature', DB::raw('COUNT(*) as count'), DB::raw('SUM(estimated_cost_usd) as spent_usd'))
            ->groupBy('feature')
            ->orderBy('feature')
            ->get();

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
            'by_model' => array_values($byModel->map(fn ($row) => [
                'model' => (string) $row->model,
                'spent_usd' => AiMoney::of((string) ($row->spent_usd ?? '0'))->toString(),
            ])->all()),
            'by_feature' => array_values($byFeature->map(fn ($row) => [
                'feature' => (string) $row->feature,
                'count' => (int) $row->count,
                'spent_usd' => AiMoney::of((string) ($row->spent_usd ?? '0'))->toString(),
            ])->all()),
        ];
    }
}
