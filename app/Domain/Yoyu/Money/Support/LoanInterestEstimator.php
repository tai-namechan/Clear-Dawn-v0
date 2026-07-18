<?php

namespace App\Domain\Yoyu\Money\Support;

/**
 * Pure monthly-compound loan payoff estimator (minor units, integer bps).
 *
 * Not advice — used for previews and rough months-to-payoff only.
 */
final class LoanInterestEstimator
{
    private const MAX_MONTHS = 1200;

    /**
     * Null when payment cannot cover first-month interest, or principal
     * never reaches zero within MAX_MONTHS.
     *
     * @return array{months: int, total_interest_minor: int}|null
     */
    public function estimate(int $principalMinor, ?int $annualInterestRateBps, int $monthlyPaymentMinor): ?array
    {
        if ($principalMinor <= 0) {
            return [
                'months' => 0,
                'total_interest_minor' => 0,
            ];
        }

        if ($monthlyPaymentMinor <= 0) {
            return null;
        }

        $balance = $principalMinor;
        $totalInterest = 0;
        $months = 0;
        $monthlyRate = ($annualInterestRateBps ?? 0) / 10000 / 12;

        while ($balance > 0 && $months < self::MAX_MONTHS) {
            $months++;
            $interest = (int) floor($balance * $monthlyRate);

            if ($monthlyPaymentMinor <= $interest) {
                return null;
            }

            $principalPaid = min($balance, $monthlyPaymentMinor - $interest);
            $totalInterest += $interest;
            $balance -= $principalPaid;
        }

        if ($balance > 0) {
            return null;
        }

        return [
            'months' => $months,
            'total_interest_minor' => $totalInterest,
        ];
    }

    /**
     * @return array{
     *     baseline_months: int|null,
     *     baseline_interest_minor: int|null,
     *     with_prepay_months: int|null,
     *     with_prepay_interest_minor: int|null,
     *     months_saved: int|null,
     *     interest_saved_minor: int|null
     * }
     */
    public function prepayPreview(
        int $principalMinor,
        ?int $annualInterestRateBps,
        int $monthlyPaymentMinor,
        int $extraPrincipalMinor,
    ): array {
        $baseline = $this->estimate($principalMinor, $annualInterestRateBps, $monthlyPaymentMinor);

        $reducedPrincipal = max(0, $principalMinor - max(0, $extraPrincipalMinor));
        $withPrepay = $this->estimate($reducedPrincipal, $annualInterestRateBps, $monthlyPaymentMinor);

        $monthsSaved = null;
        $interestSaved = null;
        if ($baseline !== null && $withPrepay !== null) {
            $monthsSaved = max(0, $baseline['months'] - $withPrepay['months']);
            $interestSaved = max(0, $baseline['total_interest_minor'] - $withPrepay['total_interest_minor']);
        }

        return [
            'baseline_months' => $baseline['months'] ?? null,
            'baseline_interest_minor' => $baseline['total_interest_minor'] ?? null,
            'with_prepay_months' => $withPrepay['months'] ?? null,
            'with_prepay_interest_minor' => $withPrepay['total_interest_minor'] ?? null,
            'months_saved' => $monthsSaved,
            'interest_saved_minor' => $interestSaved,
        ];
    }
}
