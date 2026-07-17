<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneySimulationActionType: string
{
    case DeferCashflow = 'defer_cashflow';
    case ChangeCardPayment = 'change_card_payment';
    case PauseRecurring = 'pause_recurring';
    case CapCategory = 'cap_category';
    case AddPurchase = 'add_purchase';
    case PrepayLoan = 'prepay_loan';
    case AdjustIncome = 'adjust_income';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
