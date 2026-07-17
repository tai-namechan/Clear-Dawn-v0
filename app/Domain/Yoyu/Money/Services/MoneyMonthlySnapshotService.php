<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Models\MoneyMonthlySnapshot;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MoneyMonthlySnapshotService
{
    public function __construct(
        private readonly MoneyAuditService $auditService,
        private readonly MoneyProjectionQuery $projectionQuery,
        private readonly MoneySetupService $setupService,
        private readonly UserTimezoneResolver $timezoneResolver,
    ) {}

    public function closeMonth(User $user, string $yearMonth): MoneyMonthlySnapshot
    {
        if (preg_match('/^\d{4}-\d{2}$/', $yearMonth) !== 1) {
            throw new InvalidArgumentException('yearMonth must be YYYY-MM.');
        }

        return DB::transaction(function () use ($user, $yearMonth): MoneyMonthlySnapshot {
            $settings = $this->setupService->ensureForUser($user);
            $timezone = $this->timezoneResolver->for($user);
            $projection = $this->projectionQuery->forUser($user, $yearMonth);

            /** @var MoneyMonthlySnapshot|null $previous */
            $previous = MoneyMonthlySnapshot::query()
                ->withoutUserScope()
                ->where('user_id', $user->id)
                ->where('year_month', $yearMonth)
                ->where('status', 'closed')
                ->orderByDesc('revision')
                ->lockForUpdate()
                ->first();

            $revision = 1;
            $supersedesId = null;
            if ($previous !== null) {
                $revision = (int) $previous->revision + 1;
                $supersedesId = $previous->id;
                $previous->status = 'superseded';
                $previous->save();
            }

            $asOf = CarbonImmutable::parse($yearMonth.'-01', $timezone)
                ->endOfMonth()
                ->toDateString();

            /** @var MoneyMonthlySnapshot $snapshot */
            $snapshot = MoneyMonthlySnapshot::query()->withoutUserScope()->create([
                'user_id' => $user->id,
                'year_month' => $yearMonth,
                'revision' => $revision,
                'status' => 'closed',
                'formula_version' => (string) ($projection['settings']['formula_version'] ?? $settings->formula_version),
                'as_of_date' => $asOf,
                'currency_code' => (string) ($projection['settings']['currency_code'] ?? $settings->currency_code),
                'balances_payload' => [
                    'funds_minor' => $projection['funds_minor'] ?? null,
                    'accounts' => $projection['accounts'] ?? [],
                ],
                'cashflows_payload' => [
                    'upcoming_cashflows' => $projection['upcoming_cashflows'] ?? [],
                ],
                'margin_payload' => $projection['margin'] ?? [],
                'assumptions_payload' => [
                    'settings' => $projection['settings'] ?? [],
                    'freshness_warnings' => $projection['freshness_warnings'] ?? [],
                ],
                'closed_at' => Date::now(),
                'supersedes_id' => $supersedesId,
            ]);

            $this->auditService->record(
                (int) $user->id,
                'money_monthly_snapshot.closed',
                MoneyMonthlySnapshot::class,
                (string) $snapshot->id,
                null,
                [
                    'id' => $snapshot->id,
                    'status' => $snapshot->status,
                    'supersedes_id' => $supersedesId,
                ],
            );

            return $snapshot;
        });
    }
}
