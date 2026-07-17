<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Enums\MoneyCashflowStatus;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Support\MoneyAmount;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\User;
use Carbon\CarbonImmutable;

final class MoneyProjectionQuery
{
    private const STALE_BALANCE_DAYS = 7;

    public function __construct(
        private readonly MoneySetupService $setupService,
        private readonly MarginCalculator $marginCalculator,
        private readonly MoneyReconciliationService $reconciliationService,
        private readonly UserTimezoneResolver $timezoneResolver,
        private readonly RecurringCashflowGenerator $recurringCashflowGenerator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, ?string $month = null): array
    {
        $timezone = $this->timezoneResolver->for($user);
        $now = CarbonImmutable::now($timezone);
        $asOf = $now->toDateString();

        $settings = $this->setupService->ensureForUser($user);
        $this->recurringCashflowGenerator->generateForUser($user);

        $horizonMonths = max(1, (int) ($settings->calculation_horizon_months ?? 3));
        $monthStart = $month !== null
            ? CarbonImmutable::parse($month.'-01', $timezone)->startOfMonth()
            : $now->startOfMonth();
        $horizonEnd = $month !== null
            ? $monthStart->endOfMonth()->toDateString()
            : $now->addMonthsNoOverflow($horizonMonths)->endOfMonth()->toDateString();

        $accounts = MoneyAccount::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        $fundsMinor = 0;
        $accountRows = [];
        $freshnessWarnings = [];

        foreach ($accounts as $account) {
            $balance = $account->available_balance_minor ?? $account->current_balance_minor;
            $fundsMinor += (int) $balance;

            $balanceAsOf = $account->balance_as_of !== null
                ? CarbonImmutable::parse($account->balance_as_of)->timezone($timezone)
                : null;
            $isStale = $balanceAsOf === null
                || $balanceAsOf->diffInDays($now) > self::STALE_BALANCE_DAYS;

            if ($isStale) {
                $freshnessWarnings[] = [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'balance_as_of' => $balanceAsOf?->toIso8601String(),
                    'message' => 'balance_stale_over_7_days',
                ];
            }

            $accountRows[] = [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
                'current_balance_minor' => MoneyAmount::ofMinor((int) $account->current_balance_minor)->toString(),
                'available_balance_minor' => $account->available_balance_minor !== null
                    ? MoneyAmount::ofMinor((int) $account->available_balance_minor)->toString()
                    : null,
                'balance_as_of' => $balanceAsOf?->toIso8601String(),
                'is_stale' => $isStale,
            ];
        }

        $cashflowModels = MoneyCashflow::query()
            ->withoutUserScope()
            ->with('category')
            ->where('user_id', $user->id)
            ->whereNotIn('status', [
                MoneyCashflowStatus::Settled->value,
                MoneyCashflowStatus::Canceled->value,
                MoneyCashflowStatus::Deferred->value,
            ])
            ->whereDate('due_on', '<=', $horizonEnd)
            ->orderBy('due_on')
            ->get();

        $cashflowPayload = [];
        $essentialScheduledMinor = 0;

        foreach ($cashflowModels as $cashflow) {
            $remaining = $this->reconciliationService->remainingAmountMinor($cashflow);
            $isEssential = (bool) ($cashflow->category?->is_essential ?? false);

            if (
                $cashflow->direction === MoneyDirection::Outflow
                && $isEssential
                && (string) $cashflow->due_on?->toDateString() >= $monthStart->toDateString()
                && (string) $cashflow->due_on?->toDateString() <= $monthStart->endOfMonth()->toDateString()
            ) {
                $essentialScheduledMinor += $remaining;
            }

            $cashflowPayload[] = [
                'direction' => $cashflow->direction->value,
                'amount_minor' => (int) $cashflow->amount_minor,
                'certainty' => $cashflow->certainty->value,
                'status' => $cashflow->status->value,
                'due_on' => (string) $cashflow->due_on?->toDateString(),
                'kind' => $cashflow->kind->value,
                'remaining_minor' => $remaining,
                'category_is_essential' => $isEssential,
            ];
        }

        $margin = $this->marginCalculator->calculate(
            fundsMinor: $fundsMinor,
            cashflows: $cashflowPayload,
            uncertainReserveBps: (int) $settings->uncertain_outflow_reserve_bps,
            minimumLivingBudgetMinor: $settings->minimum_living_budget_minor !== null
                ? (int) $settings->minimum_living_budget_minor
                : null,
            safetyBufferMinor: $settings->safety_buffer_minor !== null
                ? (int) $settings->safety_buffer_minor
                : null,
            essentialConsumedMinor: 0,
            essentialScheduledMinor: $essentialScheduledMinor,
            asOf: $asOf,
            horizonEnd: $horizonEnd,
            formulaVersion: (string) $settings->formula_version,
            includeExpectedIncome: (bool) $settings->include_expected_income,
        );

        $upcomingEnd = $now->addDays(7)->toDateString();
        $upcoming = [];
        foreach ($cashflowModels as $cashflow) {
            $dueOn = (string) $cashflow->due_on?->toDateString();
            if ($dueOn < $asOf || $dueOn > $upcomingEnd) {
                continue;
            }

            $upcoming[] = [
                'id' => $cashflow->id,
                'name' => $cashflow->name,
                'direction' => $cashflow->direction->value,
                'kind' => $cashflow->kind->value,
                'status' => $cashflow->status->value,
                'certainty' => $cashflow->certainty->value,
                'due_on' => $dueOn,
                'amount_minor' => MoneyAmount::ofMinor((int) $cashflow->amount_minor)->toString(),
                'remaining_minor' => MoneyAmount::ofMinor(
                    $this->reconciliationService->remainingAmountMinor($cashflow),
                )->toString(),
            ];
        }

        return [
            'as_of' => $asOf,
            'horizon_end' => $horizonEnd,
            'month' => $monthStart->format('Y-m'),
            'timezone' => $timezone,
            'funds_minor' => MoneyAmount::ofMinor($fundsMinor)->toString(),
            'settings' => [
                'minimum_living_budget_minor' => $settings->minimum_living_budget_minor !== null
                    ? MoneyAmount::ofMinor((int) $settings->minimum_living_budget_minor)->toString()
                    : null,
                'safety_buffer_minor' => $settings->safety_buffer_minor !== null
                    ? MoneyAmount::ofMinor((int) $settings->safety_buffer_minor)->toString()
                    : null,
                'uncertain_outflow_reserve_bps' => (int) $settings->uncertain_outflow_reserve_bps,
                'include_expected_income' => (bool) $settings->include_expected_income,
                'calculation_horizon_months' => (int) $settings->calculation_horizon_months,
                'formula_version' => (string) $settings->formula_version,
                'currency_code' => (string) $settings->currency_code,
            ],
            'accounts' => $accountRows,
            'margin' => $margin->toArray(),
            'upcoming_cashflows' => $upcoming,
            'freshness_warnings' => $freshnessWarnings,
        ];
    }
}
