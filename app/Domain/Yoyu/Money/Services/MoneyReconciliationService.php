<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Models\MoneyReconciliation;

final class MoneyReconciliationService
{
    public function settledAmountMinor(MoneyCashflow $cashflow): int
    {
        return (int) MoneyReconciliation::query()
            ->withoutUserScope()
            ->where('cashflow_id', $cashflow->id)
            ->sum('amount_minor');
    }

    public function remainingAmountMinor(MoneyCashflow $cashflow): int
    {
        $remaining = (int) $cashflow->amount_minor - $this->settledAmountMinor($cashflow);

        return max(0, $remaining);
    }
}
