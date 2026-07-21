<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Enums\MoneyCashflowStatus;
use App\Domain\Yoyu\Money\Enums\MoneyCertainty;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyFlexibility;
use App\Domain\Yoyu\Money\Enums\MoneyLoanStatus;
use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Models\MoneyCreditCard;
use App\Domain\Yoyu\Money\Models\MoneyLoan;
use App\Domain\Yoyu\Money\Support\MoneyAmount;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class MoneyProjectionQuery
{
    private const STALE_BALANCE_DAYS = 7;

    private const TIMELINE_LIMIT = 12;

    public function __construct(
        private readonly MoneySetupService $setupService,
        private readonly MarginCalculator $marginCalculator,
        private readonly MoneyReconciliationService $reconciliationService,
        private readonly UserTimezoneResolver $timezoneResolver,
        private readonly RecurringCashflowGenerator $recurringCashflowGenerator,
        private readonly MoneySetupProgressService $setupProgressService,
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
        $monthEnd = $monthStart->endOfMonth()->toDateString();
        $horizonEnd = $month !== null
            ? $monthEnd
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
            ->with(['category', 'counterparty'])
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
        $monthIncomeMinor = 0;
        $monthConfirmedOutflowMinor = 0;
        $monthExpectedOutflowMinor = 0;

        foreach ($cashflowModels as $cashflow) {
            $remaining = $this->reconciliationService->remainingAmountMinor($cashflow);
            $isEssential = (bool) ($cashflow->category?->is_essential ?? false);
            $dueOn = (string) $cashflow->due_on?->toDateString();

            if (
                $cashflow->direction === MoneyDirection::Outflow
                && $isEssential
                && $dueOn >= $monthStart->toDateString()
                && $dueOn <= $monthEnd
            ) {
                $essentialScheduledMinor += $remaining;
            }

            if ($dueOn >= $monthStart->toDateString() && $dueOn <= $monthEnd) {
                if ($cashflow->direction === MoneyDirection::Inflow) {
                    $monthIncomeMinor += $remaining;
                } elseif ($cashflow->certainty === MoneyCertainty::Confirmed) {
                    $monthConfirmedOutflowMinor += $remaining;
                } elseif ($cashflow->certainty === MoneyCertainty::Expected) {
                    $monthExpectedOutflowMinor += $remaining;
                }
            }

            $cashflowPayload[] = [
                'direction' => $cashflow->direction->value,
                'amount_minor' => (int) $cashflow->amount_minor,
                'certainty' => $cashflow->certainty->value,
                'status' => $cashflow->status->value,
                'due_on' => $dueOn,
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

        $dailyBalances = $this->marginCalculator->projectDailyBalances(
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
            horizonEnd: max($horizonEnd, $monthEnd),
            formulaVersion: (string) $settings->formula_version,
            includeExpectedIncome: (bool) $settings->include_expected_income,
        );

        $monthEndBalanceMinor = $fundsMinor;
        foreach ($dailyBalances as $day) {
            if ($day['date'] <= $monthEnd) {
                $monthEndBalanceMinor = (int) $day['balance_minor'];
            }
        }

        $balanceTimeline = $this->buildEventTimeline(
            $cashflowModels,
            $fundsMinor,
            (bool) $settings->include_expected_income,
            $asOf,
            $horizonEnd,
        );

        $upcomingEnd = $now->addDays(14)->toDateString();
        $upcoming = [];
        $nextIncome = null;

        foreach ($cashflowModels as $cashflow) {
            $dueOn = (string) $cashflow->due_on?->toDateString();
            $remaining = $this->reconciliationService->remainingAmountMinor($cashflow);

            if (
                $cashflow->direction === MoneyDirection::Inflow
                && $dueOn > $asOf
                && ($nextIncome === null || $dueOn < $nextIncome['due_on'])
            ) {
                $nextIncome = [
                    'id' => $cashflow->id,
                    'name' => $cashflow->name,
                    'due_on' => $dueOn,
                    'amount_minor' => MoneyAmount::ofMinor($remaining)->toString(),
                ];
            }

            if ($dueOn < $asOf || $dueOn > $upcomingEnd) {
                continue;
            }

            if ($cashflow->direction !== MoneyDirection::Outflow) {
                continue;
            }

            $balanceAfter = null;
            foreach ($balanceTimeline as $event) {
                if ($event['id'] === $cashflow->id) {
                    $balanceAfter = $event['balance_after_minor'];
                    break;
                }
            }

            $upcoming[] = [
                'id' => $cashflow->id,
                'name' => $cashflow->name,
                'direction' => $cashflow->direction->value,
                'kind' => $cashflow->kind->value,
                'status' => $cashflow->status->value,
                'certainty' => $cashflow->certainty->value,
                'flexibility' => $cashflow->flexibility->value,
                'due_on' => $dueOn,
                'amount_minor' => MoneyAmount::ofMinor((int) $cashflow->amount_minor)->toString(),
                'remaining_minor' => MoneyAmount::ofMinor($remaining)->toString(),
                'balance_after_minor' => $balanceAfter,
                'counterparty_name' => $cashflow->counterparty?->name,
                'is_adjustable' => in_array($cashflow->flexibility, [
                    MoneyFlexibility::Adjustable,
                    MoneyFlexibility::Stoppable,
                ], true),
                'lock_version' => (int) $cashflow->lock_version,
            ];
        }

        // Keep legacy shape for existing tests (7-day window, both directions).
        $legacyUpcoming = [];
        $legacyEnd = $now->addDays(7)->toDateString();
        foreach ($cashflowModels as $cashflow) {
            $dueOn = (string) $cashflow->due_on?->toDateString();
            if ($dueOn < $asOf || $dueOn > $legacyEnd) {
                continue;
            }

            $legacyUpcoming[] = [
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

        $debtSummary = $this->buildDebtSummary($user);
        $adjustmentCandidates = $this->buildAdjustmentCandidates($cashflowModels, $user);
        $setupProgress = $this->setupProgressService->forUser($user, $settings);

        $firstShortfallDate = null;
        foreach ($balanceTimeline as $event) {
            if ($event['is_shortfall'] && $firstShortfallDate === null) {
                $firstShortfallDate = $event['due_on'];
            }
        }

        return [
            'as_of' => $asOf,
            'horizon_end' => $horizonEnd,
            'month' => $monthStart->format('Y-m'),
            'month_end' => $monthEnd,
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
            'upcoming_cashflows' => $legacyUpcoming,
            'upcoming_payments' => $upcoming,
            'freshness_warnings' => $freshnessWarnings,
            'setup_progress' => $setupProgress,
            'balance_timeline' => array_slice($balanceTimeline, 0, self::TIMELINE_LIMIT),
            'month_end_balance_minor' => MoneyAmount::ofMinor($monthEndBalanceMinor)->toString(),
            'next_income' => $nextIncome,
            'first_shortfall_date' => $firstShortfallDate,
            'month_summary' => [
                'income_minor' => MoneyAmount::ofMinor($monthIncomeMinor)->toString(),
                'confirmed_outflow_minor' => MoneyAmount::ofMinor($monthConfirmedOutflowMinor)->toString(),
                'expected_outflow_minor' => MoneyAmount::ofMinor($monthExpectedOutflowMinor)->toString(),
            ],
            'debt_summary' => $debtSummary,
            'adjustment_candidates' => $adjustmentCandidates,
            // Credit limits are never included in funds — exposed only as credit info.
            'credit_facility_note' => 'card_available_excluded_from_funds',
        ];
    }

    /**
     * @param  Collection<int, MoneyCashflow>  $cashflowModels
     * @return list<array{
     *     id: string,
     *     due_on: string,
     *     name: string,
     *     direction: string,
     *     amount_minor: string,
     *     signed_amount_minor: string,
     *     balance_after_minor: string,
     *     is_shortfall: bool,
     *     flexibility: string,
     *     certainty: string
     * }>
     */
    private function buildEventTimeline(
        $cashflowModels,
        int $fundsMinor,
        bool $includeExpectedIncome,
        string $asOf,
        string $horizonEnd,
    ): array {
        $events = [];

        foreach ($cashflowModels as $cashflow) {
            $dueOn = (string) $cashflow->due_on?->toDateString();
            if ($dueOn === '' || $dueOn > $horizonEnd) {
                continue;
            }

            $remaining = $this->reconciliationService->remainingAmountMinor($cashflow);
            if ($remaining <= 0) {
                continue;
            }

            $certainty = $cashflow->certainty;
            $direction = $cashflow->direction;

            if ($direction === MoneyDirection::Inflow) {
                if ($certainty === MoneyCertainty::Confirmed) {
                    // Keep timeline aligned with margin: same-day income is deferred to asOf+effectively shown.
                    $effectiveDate = $dueOn <= $asOf ? $asOf : $dueOn;
                } elseif ($includeExpectedIncome && $certainty === MoneyCertainty::Expected) {
                    $effectiveDate = $dueOn <= $asOf ? $asOf : $dueOn;
                } else {
                    continue;
                }
                $signed = $remaining;
                $order = 1;
            } elseif ($direction === MoneyDirection::Outflow) {
                if ($certainty === MoneyCertainty::Confirmed) {
                    $amount = $remaining;
                } elseif ($certainty === MoneyCertainty::Expected) {
                    // Show expected at face value on timeline; margin still applies reserve separately.
                    $amount = $remaining;
                } else {
                    continue;
                }
                $effectiveDate = $dueOn <= $asOf ? $asOf : $dueOn;
                $signed = -$amount;
                $order = 0;
            } else {
                continue;
            }

            // For user-facing timeline, prefer the original due_on label.
            $events[] = [
                'id' => $cashflow->id,
                'sort_date' => $effectiveDate,
                'due_on' => $dueOn,
                'name' => $cashflow->name,
                'direction' => $direction->value,
                'amount' => abs($signed),
                'signed' => $signed,
                'order' => $order,
                'flexibility' => $cashflow->flexibility->value,
                'certainty' => $certainty->value,
            ];
        }

        usort($events, function (array $a, array $b): int {
            return [$a['sort_date'], $a['order'], $a['name']] <=> [$b['sort_date'], $b['order'], $b['name']];
        });

        $balance = $fundsMinor;
        $timeline = [];

        foreach ($events as $event) {
            // Skip past-only display noise: show from asOf forward by due_on label.
            if ($event['due_on'] < $asOf && $event['sort_date'] === $asOf && $event['direction'] === MoneyDirection::Inflow->value) {
                // overdue income already treated as asOf — still show if due was today-ish; skip older noise
                if ($event['due_on'] < $asOf) {
                    $balance += (int) $event['signed'];

                    continue;
                }
            }

            $balance += (int) $event['signed'];

            if ($event['due_on'] < $asOf) {
                continue;
            }

            $timeline[] = [
                'id' => $event['id'],
                'due_on' => $event['due_on'],
                'name' => $event['name'],
                'direction' => $event['direction'],
                'amount_minor' => MoneyAmount::ofMinor((int) $event['amount'])->toString(),
                'signed_amount_minor' => MoneyAmount::ofMinor((int) $event['signed'])->toString(),
                'balance_after_minor' => MoneyAmount::ofMinor($balance)->toString(),
                'is_shortfall' => $balance < 0,
                'flexibility' => $event['flexibility'],
                'certainty' => $event['certainty'],
            ];
        }

        return $timeline;
    }

    /**
     * @return array{
     *     outstanding_debt_minor: string,
     *     monthly_repayment_minor: string,
     *     card_statement_minor: string,
     *     next_repayment_on: string|null,
     *     credit_available_minor: string|null,
     *     credit_limit_minor: string|null
     * }
     */
    private function buildDebtSummary(User $user): array
    {
        $loans = MoneyLoan::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('status', MoneyLoanStatus::Active->value)
            ->get();

        $cards = MoneyCreditCard::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        $outstanding = 0;
        $monthly = 0;
        $nextRepayment = null;

        foreach ($loans as $loan) {
            $outstanding += (int) $loan->outstanding_principal_minor;
            $monthly += (int) $loan->monthly_payment_minor;
            $next = $loan->next_payment_on?->toDateString();
            if ($next !== null && ($nextRepayment === null || $next < $nextRepayment)) {
                $nextRepayment = $next;
            }
        }

        $cardStatement = 0;
        $creditAvailable = 0;
        $creditLimit = 0;
        $hasCreditInfo = false;

        foreach ($cards as $card) {
            $cardStatement += (int) ($card->current_statement_minor ?? 0);
            $outstanding += (int) ($card->revolving_balance_minor ?? 0);
            $outstanding += (int) ($card->installment_balance_minor ?? 0);

            if ($card->available_minor !== null) {
                $creditAvailable += (int) $card->available_minor;
                $hasCreditInfo = true;
            }
            if ($card->limit_minor !== null) {
                $creditLimit += (int) $card->limit_minor;
                $hasCreditInfo = true;
            }
        }

        return [
            'outstanding_debt_minor' => MoneyAmount::ofMinor($outstanding)->toString(),
            'monthly_repayment_minor' => MoneyAmount::ofMinor($monthly)->toString(),
            'card_statement_minor' => MoneyAmount::ofMinor($cardStatement)->toString(),
            'next_repayment_on' => $nextRepayment,
            // Exposed separately — never added to funds_minor / 現在の資金.
            'credit_available_minor' => $hasCreditInfo
                ? MoneyAmount::ofMinor($creditAvailable)->toString()
                : null,
            'credit_limit_minor' => $hasCreditInfo
                ? MoneyAmount::ofMinor($creditLimit)->toString()
                : null,
        ];
    }

    /**
     * @param  Collection<int, MoneyCashflow>  $cashflowModels
     * @return list<array{
     *     id: string,
     *     type: string,
     *     title: string,
     *     detail: string,
     *     amount_minor: string|null,
     *     href: string,
     *     simulate_href: string
     * }>
     */
    private function buildAdjustmentCandidates($cashflowModels, User $user): array
    {
        $candidates = [];

        foreach ($cashflowModels as $cashflow) {
            if ($cashflow->direction !== MoneyDirection::Outflow) {
                continue;
            }

            $remaining = $this->reconciliationService->remainingAmountMinor($cashflow);
            if ($remaining <= 0) {
                continue;
            }

            if ($cashflow->flexibility === MoneyFlexibility::Stoppable) {
                $candidates[] = [
                    'id' => 'stop-'.$cashflow->id,
                    'type' => 'stoppable',
                    'title' => $cashflow->name.'を停止・休会すると',
                    'detail' => '毎月の支払いを見直した場合の余裕を比較できます',
                    'amount_minor' => MoneyAmount::ofMinor($remaining)->toString(),
                    'href' => '/yoyu/money/cashflows?highlight='.$cashflow->id,
                    'simulate_href' => '/yoyu/money/simulations?from_cashflow='.$cashflow->id.'&action=pause',
                ];
            } elseif ($cashflow->flexibility === MoneyFlexibility::Adjustable) {
                $candidates[] = [
                    'id' => 'adjust-'.$cashflow->id,
                    'type' => 'adjustable',
                    'title' => $cashflow->name.'を調整すると',
                    'detail' => '支払時期や金額を変えた場合の残高を比較できます',
                    'amount_minor' => MoneyAmount::ofMinor($remaining)->toString(),
                    'href' => '/yoyu/money/cashflows?highlight='.$cashflow->id,
                    'simulate_href' => '/yoyu/money/simulations?from_cashflow='.$cashflow->id.'&action=defer',
                ];
            }

            if (count($candidates) >= 5) {
                break;
            }
        }

        if (count($candidates) < 5) {
            $loans = MoneyLoan::query()
                ->withoutUserScope()
                ->where('user_id', $user->id)
                ->where('status', MoneyLoanStatus::Active->value)
                ->where('outstanding_principal_minor', '>', 0)
                ->orderByDesc('monthly_payment_minor')
                ->limit(2)
                ->get();

            foreach ($loans as $loan) {
                $candidates[] = [
                    'id' => 'loan-'.$loan->id,
                    'type' => 'loan_prepay',
                    'title' => $loan->name.'を繰上返済すると',
                    'detail' => '毎月の返済額と利息負担の見通しを比較できます',
                    'amount_minor' => MoneyAmount::ofMinor((int) $loan->monthly_payment_minor)->toString(),
                    'href' => '/yoyu/money/loans',
                    'simulate_href' => '/yoyu/money/simulations?from_loan='.$loan->id.'&action=prepay',
                ];
            }
        }

        return array_slice($candidates, 0, 5);
    }
}
