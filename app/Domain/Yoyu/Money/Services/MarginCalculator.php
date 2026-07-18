<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Data\MarginResult;
use App\Domain\Yoyu\Money\Enums\MoneyCashflowStatus;
use App\Domain\Yoyu\Money\Enums\MoneyCertainty;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Support\MoneyAmount;
use Carbon\CarbonImmutable;

/**
 * Pure margin calculation for 「お金の余裕」. No Eloquent I/O.
 */
final class MarginCalculator
{
    /**
     * @param  list<array{
     *     direction: string,
     *     amount_minor: int,
     *     certainty: string,
     *     status: string,
     *     due_on: string,
     *     kind?: string,
     *     remaining_minor?: int|null,
     *     category_is_essential?: bool|null
     * }>  $cashflows
     */
    public function calculate(
        int $fundsMinor,
        array $cashflows,
        int $uncertainReserveBps,
        ?int $minimumLivingBudgetMinor,
        ?int $safetyBufferMinor,
        int $essentialConsumedMinor,
        int $essentialScheduledMinor,
        string $asOf,
        string $horizonEnd,
        string $formulaVersion = '1',
        bool $includeExpectedIncome = false,
    ): MarginResult {
        $confirmedIncome = 0;
        $confirmedOutflow = 0;
        $expectedOutflow = 0;
        $expectedIncome = 0;

        foreach ($cashflows as $cashflow) {
            if (! $this->isUnsettled($cashflow['status'] ?? '')) {
                continue;
            }

            $remaining = $this->remainingMinor($cashflow);
            if ($remaining <= 0) {
                continue;
            }

            $direction = (string) ($cashflow['direction'] ?? '');
            $certainty = (string) ($cashflow['certainty'] ?? '');
            $dueOn = (string) ($cashflow['due_on'] ?? '');

            if ($direction === MoneyDirection::Inflow->value) {
                if (! $this->isInflowInWindow($dueOn, $asOf, $horizonEnd)) {
                    continue;
                }

                if ($certainty === MoneyCertainty::Confirmed->value) {
                    $confirmedIncome += $remaining;
                } elseif ($includeExpectedIncome && $certainty === MoneyCertainty::Expected->value) {
                    $expectedIncome += $remaining;
                }

                continue;
            }

            if ($direction === MoneyDirection::Outflow->value) {
                // Overdue outflows (due_on <= as_of) remain in scope through horizon_end.
                if (! $this->isOutflowInWindow($dueOn, $horizonEnd)) {
                    continue;
                }

                if ($certainty === MoneyCertainty::Confirmed->value) {
                    $confirmedOutflow += $remaining;
                } elseif ($certainty === MoneyCertainty::Expected->value) {
                    $expectedOutflow += $remaining;
                }
            }
        }

        $Ic = $confirmedIncome + ($includeExpectedIncome ? $expectedIncome : 0);
        $Oc = $confirmedOutflow;
        $Oe = intdiv($expectedOutflow * max(0, $uncertainReserveBps), 10000);

        $livingReserve = 0;
        if ($minimumLivingBudgetMinor !== null) {
            $livingReserve = max(
                0,
                $minimumLivingBudgetMinor - $essentialConsumedMinor - $essentialScheduledMinor,
            );
        }

        $safety = $safetyBufferMinor ?? 0;

        $projectedCash = $fundsMinor + $Ic - $Oc - $Oe;
        $projectedMargin = $projectedCash - $livingReserve - $safety;
        $safeToSpend = max(0, $projectedMargin);
        $shortfall = max(0, -$projectedMargin);

        $missingSettings = [];
        if ($minimumLivingBudgetMinor === null) {
            $missingSettings[] = 'minimum_living_budget_minor';
        }
        if ($safetyBufferMinor === null) {
            $missingSettings[] = 'safety_buffer_minor';
        }

        $warnings = [];
        // Card limit must never be included in funds — documented for consumers.
        $warnings[] = 'card_limit_excluded';

        return new MarginResult(
            fundsMinor: MoneyAmount::ofMinor($fundsMinor)->toString(),
            confirmedIncomeMinor: MoneyAmount::ofMinor($Ic)->toString(),
            confirmedOutflowMinor: MoneyAmount::ofMinor($Oc)->toString(),
            uncertainReserveMinor: MoneyAmount::ofMinor($Oe)->toString(),
            livingReserveMinor: MoneyAmount::ofMinor($livingReserve)->toString(),
            safetyBufferMinor: MoneyAmount::ofMinor($safety)->toString(),
            projectedCashMinor: MoneyAmount::ofMinor($projectedCash)->toString(),
            projectedMarginMinor: MoneyAmount::ofMinor($projectedMargin)->toString(),
            safeToSpendMinor: MoneyAmount::ofMinor($safeToSpend)->toString(),
            shortfallMinor: MoneyAmount::ofMinor($shortfall)->toString(),
            formulaVersion: $formulaVersion,
            asOf: $asOf,
            horizonEnd: $horizonEnd,
            isComplete: $missingSettings === [],
            missingSettings: $missingSettings,
            warnings: $warnings,
            breakdown: [
                'funds_minor' => $fundsMinor,
                'confirmed_income_minor' => $Ic,
                'confirmed_outflow_minor' => $Oc,
                'expected_outflow_gross_minor' => $expectedOutflow,
                'uncertain_reserve_bps' => $uncertainReserveBps,
                'uncertain_reserve_minor' => $Oe,
                'expected_income_minor' => $includeExpectedIncome ? $expectedIncome : 0,
                'include_expected_income' => $includeExpectedIncome,
                'essential_consumed_minor' => $essentialConsumedMinor,
                'essential_scheduled_minor' => $essentialScheduledMinor,
                'living_input_minor' => $minimumLivingBudgetMinor,
                'safety_input_minor' => $safetyBufferMinor,
            ],
        );
    }

    /**
     * Project daily balances applying outflows before inflows on the same day.
     *
     * @param  list<array{
     *     direction: string,
     *     amount_minor: int,
     *     certainty: string,
     *     status: string,
     *     due_on: string,
     *     kind?: string,
     *     remaining_minor?: int|null,
     *     category_is_essential?: bool|null
     * }>  $cashflows
     * @return list<array{date: string, balance_minor: int, margin_minor: int}>
     */
    public function projectDailyBalances(
        int $fundsMinor,
        array $cashflows,
        int $uncertainReserveBps,
        ?int $minimumLivingBudgetMinor,
        ?int $safetyBufferMinor,
        int $essentialConsumedMinor,
        int $essentialScheduledMinor,
        string $asOf,
        string $horizonEnd,
        string $formulaVersion = '1',
        bool $includeExpectedIncome = false,
    ): array {
        $unsettled = [];
        foreach ($cashflows as $cashflow) {
            if (! $this->isUnsettled($cashflow['status'] ?? '')) {
                continue;
            }
            $remaining = $this->remainingMinor($cashflow);
            if ($remaining <= 0) {
                continue;
            }
            $dueOn = (string) ($cashflow['due_on'] ?? '');
            if ($dueOn === '' || $dueOn > $horizonEnd) {
                continue;
            }

            $direction = (string) ($cashflow['direction'] ?? '');
            $certainty = (string) ($cashflow['certainty'] ?? '');

            if ($direction === MoneyDirection::Inflow->value) {
                if ($certainty === MoneyCertainty::Confirmed->value
                    || ($includeExpectedIncome && $certainty === MoneyCertainty::Expected->value)) {
                    $effectiveDate = $dueOn <= $asOf ? $asOf : $dueOn;
                    $unsettled[] = [
                        'date' => $effectiveDate,
                        'direction' => $direction,
                        'amount' => $remaining,
                        'order' => 1,
                    ];
                }

                continue;
            }

            if ($direction === MoneyDirection::Outflow->value) {
                $amount = $remaining;
                if ($certainty === MoneyCertainty::Expected->value) {
                    $amount = intdiv($remaining * max(0, $uncertainReserveBps), 10000);
                } elseif ($certainty !== MoneyCertainty::Confirmed->value) {
                    continue;
                }

                $effectiveDate = $dueOn <= $asOf ? $asOf : $dueOn;
                $unsettled[] = [
                    'date' => $effectiveDate,
                    'direction' => $direction,
                    'amount' => $amount,
                    'order' => 0,
                ];
            }
        }

        usort($unsettled, function (array $a, array $b): int {
            return [$a['date'], $a['order']] <=> [$b['date'], $b['order']];
        });

        $dates = [];
        $cursor = CarbonImmutable::parse($asOf)->startOfDay();
        $end = CarbonImmutable::parse($horizonEnd)->startOfDay();
        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->addDay();
        }

        $byDate = [];
        foreach ($unsettled as $item) {
            $byDate[$item['date']][] = $item;
        }

        $balance = $fundsMinor;
        $living = $minimumLivingBudgetMinor;
        $safety = $safetyBufferMinor;
        $result = [];

        foreach ($dates as $date) {
            foreach ($byDate[$date] ?? [] as $item) {
                if ($item['direction'] === MoneyDirection::Outflow->value) {
                    $balance -= (int) $item['amount'];
                } else {
                    $balance += (int) $item['amount'];
                }
            }

            $marginResult = $this->calculate(
                fundsMinor: $balance,
                cashflows: array_values(array_filter(
                    $cashflows,
                    fn (array $cf): bool => (string) ($cf['due_on'] ?? '') > $date,
                )),
                uncertainReserveBps: $uncertainReserveBps,
                minimumLivingBudgetMinor: $living,
                safetyBufferMinor: $safety,
                essentialConsumedMinor: $essentialConsumedMinor,
                essentialScheduledMinor: $essentialScheduledMinor,
                asOf: $date,
                horizonEnd: $horizonEnd,
                formulaVersion: $formulaVersion,
                includeExpectedIncome: $includeExpectedIncome,
            );

            $result[] = [
                'date' => $date,
                'balance_minor' => $balance,
                'margin_minor' => (int) $marginResult->projectedMarginMinor,
            ];
        }

        return $result;
    }

    private function isUnsettled(string $status): bool
    {
        return ! in_array($status, [
            MoneyCashflowStatus::Settled->value,
            MoneyCashflowStatus::Canceled->value,
            MoneyCashflowStatus::Deferred->value,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $cashflow
     */
    private function remainingMinor(array $cashflow): int
    {
        if (array_key_exists('remaining_minor', $cashflow) && $cashflow['remaining_minor'] !== null) {
            return max(0, (int) $cashflow['remaining_minor']);
        }

        return max(0, (int) ($cashflow['amount_minor'] ?? 0));
    }

    /**
     * Confirmed/expected inflows in (as_of, horizon_end].
     */
    private function isInflowInWindow(string $dueOn, string $asOf, string $horizonEnd): bool
    {
        return $dueOn > $asOf && $dueOn <= $horizonEnd;
    }

    /**
     * Outflows due on/before horizon (overdue due_on <= as_of remain included).
     */
    private function isOutflowInWindow(string $dueOn, string $horizonEnd): bool
    {
        return $dueOn !== '' && $dueOn <= $horizonEnd;
    }
}
