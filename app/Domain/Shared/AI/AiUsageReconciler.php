<?php

namespace App\Domain\Shared\AI;

use App\Domain\Shared\Models\AiUsageLog;
use App\Domain\Shared\Models\AiUsageMonthly;
use App\Enums\AiUsageRequestStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class AiUsageReconciler
{
    public function __construct(
        private AiUsagePeriodResolver $periods,
        private AiUsageLedger $ledger,
    ) {}

    /**
     * Idempotently align monthly spent_usd with settled logs + expired charges for a period.
     *
     * @return array{users: int, adjusted: int}
     */
    public function reconcilePeriod(string $period): array
    {
        $userIds = AiUsageLog::query()
            ->withoutUserScope()
            ->whereBetween('created_at', array_map(
                fn ($bound) => $bound->toDateTimeString(),
                $this->periods->utcBounds($period),
            ))
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id')
            ->merge(
                AiUsageMonthly::query()
                    ->withoutUserScope()
                    ->where('period', $period)
                    ->orderBy('user_id')
                    ->pluck('user_id')
            )
            ->unique()
            ->values();

        $adjusted = 0;

        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            $monthly = $this->ledger->ensureMonthly($userId, $period);

            $logsSpent = $this->ledger->sumExistingLogs($userId, $period);
            $expiredCharged = AiMoney::fromAggregate(
                DB::table('ai_usage_requests')
                    ->where('user_id', $userId)
                    ->where('period', $period)
                    ->where('status', AiUsageRequestStatus::Expired->value)
                    ->selectRaw('COALESCE(SUM(charged_usd), 0) as total')
                    ->value('total')
            );

            $expectedSpent = $logsSpent->add($expiredCharged);
            $currentSpent = AiMoney::of((string) $monthly->spent_usd);

            if ($currentSpent->compare($expectedSpent) === 0) {
                continue;
            }

            DB::transaction(function () use ($userId, $period, $expectedSpent, $monthly): void {
                $locked = AiUsageMonthly::query()
                    ->withoutUserScope()
                    ->whereKey($monthly->id)
                    ->where('user_id', $userId)
                    ->where('period', $period)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null) {
                    return;
                }

                $locked->update([
                    'spent_usd' => $expectedSpent->toString(),
                ]);
            });

            $adjusted++;

            Log::info('AI usage monthly reconciled.', [
                'user_id' => $userId,
                'period' => $period,
                'from' => $currentSpent->toString(),
                'to' => $expectedSpent->toString(),
            ]);
        }

        return [
            'users' => $userIds->count(),
            'adjusted' => $adjusted,
        ];
    }
}
