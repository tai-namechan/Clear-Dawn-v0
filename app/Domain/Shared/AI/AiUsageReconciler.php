<?php

namespace App\Domain\Shared\AI;

use App\Domain\Shared\Models\AiUsageLog;
use App\Domain\Shared\Models\AiUsageMonthly;
use App\Domain\Shared\Models\AiUsageRequest;
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
     * Idempotently align monthly spent_usd with canonical spent for a period.
     *
     * @return array{users: int, adjusted: int}
     */
    public function reconcilePeriod(string $period): array
    {
        [$from, $to] = $this->periods->utcBounds($period);

        $userIds = AiUsageLog::query()
            ->withoutUserScope()
            ->whereNull('usage_request_id')
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id')
            ->merge(
                AiUsageRequest::query()
                    ->withoutUserScope()
                    ->where('period', $period)
                    ->whereIn('status', [
                        AiUsageRequestStatus::Settled->value,
                        AiUsageRequestStatus::Expired->value,
                    ])
                    ->distinct()
                    ->orderBy('user_id')
                    ->pluck('user_id')
            )
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
            $this->ledger->ensureMonthly($userId, $period);

            $didAdjust = false;
            $fromAmount = null;
            $toAmount = null;

            DB::transaction(function () use ($userId, $period, &$didAdjust, &$fromAmount, &$toAmount): void {
                $locked = AiUsageMonthly::query()
                    ->withoutUserScope()
                    ->where('user_id', $userId)
                    ->where('period', $period)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null) {
                    return;
                }

                // Aggregate without locking request rows to avoid deadlock with settle.
                $expectedSpent = $this->ledger->sumCanonicalSpent($userId, $period);
                $currentSpent = AiMoney::of((string) $locked->spent_usd);

                if ($currentSpent->compare($expectedSpent) === 0) {
                    return;
                }

                $locked->update([
                    'spent_usd' => $expectedSpent->toString(),
                ]);

                $didAdjust = true;
                $fromAmount = $currentSpent->toString();
                $toAmount = $expectedSpent->toString();
            });

            if (! $didAdjust) {
                continue;
            }

            $adjusted++;

            Log::info('AI usage monthly reconciled.', [
                'user_id' => $userId,
                'period' => $period,
                'from' => $fromAmount,
                'to' => $toAmount,
            ]);
        }

        return [
            'users' => $userIds->count(),
            'adjusted' => $adjusted,
        ];
    }
}
