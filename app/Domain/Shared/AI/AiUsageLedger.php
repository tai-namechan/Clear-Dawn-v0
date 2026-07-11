<?php

namespace App\Domain\Shared\AI;

use App\Domain\Shared\Models\AiUsageLog;
use App\Domain\Shared\Models\AiUsageMonthly;
use App\Domain\Shared\Models\AiUsageRequest;
use App\Enums\AiUsageRequestStatus;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class AiUsageLedger
{
    public function __construct(
        private AiUsagePeriodResolver $periods,
        private AiCostCalculator $costs,
    ) {}

    public function reserve(
        int $userId,
        string $feature,
        string $model,
        AiMoney $estimated,
        ?string $period = null,
    ): AiUsageRequest {
        if ($estimated->isZero() || $estimated->isNegative()) {
            throw new RuntimeException('AI reservation estimate must be positive.');
        }

        $period ??= $this->periods->periodFor();
        $limit = $this->costs->monthlyLimit();

        $this->ensureMonthly($userId, $period);

        return DB::transaction(function () use ($userId, $feature, $model, $estimated, $period, $limit): AiUsageRequest {
            $now = now()->toDateTimeString();
            $affected = DB::update(
                'update ai_usage_monthly
                 set reserved_usd = CAST(reserved_usd AS DECIMAL(12,6)) + CAST(? AS DECIMAL(12,6)),
                     updated_at = ?
                 where user_id = ?
                   and period = ?
                   and CAST(spent_usd AS DECIMAL(12,6)) + CAST(reserved_usd AS DECIMAL(12,6)) + CAST(? AS DECIMAL(12,6))
                       <= CAST(? AS DECIMAL(12,6))',
                [
                    $estimated->toString(),
                    $now,
                    $userId,
                    $period,
                    $estimated->toString(),
                    $limit->toString(),
                ],
            );

            if ($affected !== 1) {
                throw new QuotaExceededException;
            }

            return AiUsageRequest::query()->withoutUserScope()->create([
                'user_id' => $userId,
                'period' => $period,
                'feature' => $feature,
                'model' => $model,
                'estimated_usd' => $estimated->toString(),
                'status' => AiUsageRequestStatus::Reserved,
            ]);
        });
    }

    public function markInFlight(string $requestId): AiUsageRequest
    {
        return DB::transaction(function () use ($requestId): AiUsageRequest {
            $request = $this->lockRequest($requestId);

            if ($request->status === AiUsageRequestStatus::InFlight) {
                return $request;
            }

            if ($request->status->isTerminal()) {
                return $request;
            }

            if ($request->status !== AiUsageRequestStatus::Reserved) {
                throw new RuntimeException("Cannot mark AI usage request [{$requestId}] in_flight from {$request->status->value}.");
            }

            $request->update([
                'status' => AiUsageRequestStatus::InFlight,
                'provider_started_at' => now(),
            ]);

            return $request->refresh();
        });
    }

    public function settle(
        string $requestId,
        AiMoney $actual,
        int $inputTokens,
        int $outputTokens,
    ): AiUsageRequest {
        return DB::transaction(function () use ($requestId, $actual, $inputTokens, $outputTokens): AiUsageRequest {
            $request = $this->lockRequest($requestId);

            if ($request->status === AiUsageRequestStatus::Settled) {
                return $request;
            }

            if ($request->status->isTerminal()) {
                return $request;
            }

            if (! in_array($request->status, [AiUsageRequestStatus::Reserved, AiUsageRequestStatus::InFlight], true)) {
                throw new RuntimeException("Cannot settle AI usage request [{$requestId}] from {$request->status->value}.");
            }

            $monthly = $this->lockMonthly((int) $request->user_id, $request->period);
            $estimated = AiMoney::of((string) $request->estimated_usd);
            $this->assertReservedCovers($monthly, $estimated, $requestId);

            $newReserved = AiMoney::of((string) $monthly->reserved_usd)->sub($estimated);
            $newSpent = AiMoney::of((string) $monthly->spent_usd)->add($actual);

            if ($newReserved->isNegative()) {
                Log::critical('AI usage reserved_usd would become negative on settle.', [
                    'usage_request_id' => $requestId,
                    'user_id' => $request->user_id,
                    'period' => $request->period,
                ]);
                throw new RuntimeException('AI usage reserved_usd invariant violated on settle.');
            }

            $monthly->update([
                'reserved_usd' => $newReserved->toString(),
                'spent_usd' => $newSpent->toString(),
            ]);

            $request->update([
                'status' => AiUsageRequestStatus::Settled,
                'actual_usd' => $actual->toString(),
                'charged_usd' => $actual->toString(),
                'finished_at' => now(),
                'failure_code' => null,
            ]);

            $this->createUsageLogOnce($request->fresh(), $actual, $inputTokens, $outputTokens);

            return $request->refresh();
        });
    }

    public function release(string $requestId, ?string $failureCode = null): AiUsageRequest
    {
        return DB::transaction(function () use ($requestId, $failureCode): AiUsageRequest {
            $request = $this->lockRequest($requestId);

            if ($request->status === AiUsageRequestStatus::Released) {
                return $request;
            }

            if ($request->status->isTerminal()) {
                return $request;
            }

            $monthly = $this->lockMonthly((int) $request->user_id, $request->period);
            $estimated = AiMoney::of((string) $request->estimated_usd);
            $this->assertReservedCovers($monthly, $estimated, $requestId);

            $newReserved = AiMoney::of((string) $monthly->reserved_usd)->sub($estimated);
            if ($newReserved->isNegative()) {
                Log::critical('AI usage reserved_usd would become negative on release.', [
                    'usage_request_id' => $requestId,
                    'user_id' => $request->user_id,
                    'period' => $request->period,
                ]);
                throw new RuntimeException('AI usage reserved_usd invariant violated on release.');
            }

            $monthly->update([
                'reserved_usd' => $newReserved->toString(),
            ]);

            $request->update([
                'status' => AiUsageRequestStatus::Released,
                'finished_at' => now(),
                'failure_code' => $failureCode,
                'charged_usd' => '0.000000',
            ]);

            return $request->refresh();
        });
    }

    public function expire(string $requestId, ?string $failureCode = 'stale_in_flight'): AiUsageRequest
    {
        return DB::transaction(function () use ($requestId, $failureCode): AiUsageRequest {
            $request = $this->lockRequest($requestId);

            if ($request->status === AiUsageRequestStatus::Expired) {
                return $request;
            }

            if ($request->status->isTerminal()) {
                return $request;
            }

            if ($request->status !== AiUsageRequestStatus::InFlight) {
                throw new RuntimeException("Cannot expire AI usage request [{$requestId}] from {$request->status->value}.");
            }

            $monthly = $this->lockMonthly((int) $request->user_id, $request->period);
            $estimated = AiMoney::of((string) $request->estimated_usd);
            $this->assertReservedCovers($monthly, $estimated, $requestId);

            $newReserved = AiMoney::of((string) $monthly->reserved_usd)->sub($estimated);
            $newSpent = AiMoney::of((string) $monthly->spent_usd)->add($estimated);

            if ($newReserved->isNegative()) {
                Log::critical('AI usage reserved_usd would become negative on expire.', [
                    'usage_request_id' => $requestId,
                    'user_id' => $request->user_id,
                    'period' => $request->period,
                ]);
                throw new RuntimeException('AI usage reserved_usd invariant violated on expire.');
            }

            $monthly->update([
                'reserved_usd' => $newReserved->toString(),
                'spent_usd' => $newSpent->toString(),
            ]);

            $request->update([
                'status' => AiUsageRequestStatus::Expired,
                'actual_usd' => null,
                'charged_usd' => $estimated->toString(),
                'finished_at' => now(),
                'failure_code' => $failureCode,
            ]);

            return $request->refresh();
        });
    }

    public function ensureMonthly(int $userId, string $period): AiUsageMonthly
    {
        $existing = AiUsageMonthly::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('period', $period)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $spent = $this->sumCanonicalSpent($userId, $period);

        try {
            return AiUsageMonthly::query()->withoutUserScope()->create([
                'user_id' => $userId,
                'period' => $period,
                'spent_usd' => $spent->toString(),
                'reserved_usd' => AiMoney::zero()->toString(),
            ]);
        } catch (UniqueConstraintViolationException|QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            $winner = AiUsageMonthly::query()
                ->withoutUserScope()
                ->where('user_id', $userId)
                ->where('period', $period)
                ->first();

            if ($winner === null) {
                throw $e;
            }

            return $winner;
        }
    }

    /**
     * Source of truth for spent_usd:
     * - ledger-era: SUM(charged_usd) for settled|expired requests
     * - pre-ledger: SUM(estimated_cost_usd) for logs with usage_request_id IS NULL
     * Never double-count request-linked logs.
     */
    public function sumCanonicalSpent(int $userId, string $period): AiMoney
    {
        return $this->sumLegacyLogs($userId, $period)
            ->add($this->sumChargedRequests($userId, $period));
    }

    /**
     * @deprecated Prefer sumCanonicalSpent(); kept for callers that need legacy-only.
     */
    public function sumExistingLogs(int $userId, string $period): AiMoney
    {
        return $this->sumLegacyLogs($userId, $period);
    }

    public function sumLegacyLogs(int $userId, string $period): AiMoney
    {
        [$from, $to] = $this->periods->utcBounds($period);

        $sum = AiUsageLog::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->whereNull('usage_request_id')
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->selectRaw('COALESCE(SUM(estimated_cost_usd), 0) as total')
            ->value('total');

        return AiMoney::fromAggregate($sum);
    }

    public function sumChargedRequests(int $userId, string $period): AiMoney
    {
        $sum = AiUsageRequest::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('period', $period)
            ->whereIn('status', [
                AiUsageRequestStatus::Settled->value,
                AiUsageRequestStatus::Expired->value,
            ])
            ->selectRaw('COALESCE(SUM(charged_usd), 0) as total')
            ->value('total');

        return AiMoney::fromAggregate($sum);
    }

    private function createUsageLogOnce(
        AiUsageRequest $request,
        AiMoney $actual,
        int $inputTokens,
        int $outputTokens,
    ): void {
        $exists = AiUsageLog::query()
            ->withoutUserScope()
            ->where('usage_request_id', $request->id)
            ->exists();

        if ($exists) {
            return;
        }

        AiUsageLog::query()->withoutUserScope()->create([
            'usage_request_id' => $request->id,
            'user_id' => $request->user_id,
            'feature' => $request->feature,
            'model' => $request->model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost_usd' => $actual->toString(),
            'created_at' => now(),
        ]);
    }

    private function lockRequest(string $requestId): AiUsageRequest
    {
        $request = AiUsageRequest::query()
            ->withoutUserScope()
            ->whereKey($requestId)
            ->lockForUpdate()
            ->first();

        if ($request === null) {
            throw new RuntimeException("AI usage request [{$requestId}] not found.");
        }

        return $request;
    }

    private function lockMonthly(int $userId, string $period): AiUsageMonthly
    {
        $monthly = AiUsageMonthly::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('period', $period)
            ->lockForUpdate()
            ->first();

        if ($monthly === null) {
            throw new RuntimeException("AI usage monthly row missing for user [{$userId}] period [{$period}].");
        }

        return $monthly;
    }

    private function assertReservedCovers(AiUsageMonthly $monthly, AiMoney $estimated, string $requestId): void
    {
        $reserved = AiMoney::of((string) $monthly->reserved_usd);
        if ($reserved->compare($estimated) === -1) {
            Log::critical('AI usage reserved_usd is less than request estimate.', [
                'usage_request_id' => $requestId,
                'reserved_usd' => $reserved->toString(),
                'estimated_usd' => $estimated->toString(),
            ]);
            throw new RuntimeException('AI usage reserved_usd invariant violated.');
        }
    }

    private function isUniqueViolation(Throwable $e): bool
    {
        if ($e instanceof UniqueConstraintViolationException) {
            return true;
        }

        $message = $e->getMessage();

        return str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry');
    }
}
