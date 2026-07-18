<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyTransactionKind: string
{
    case Purchase = 'purchase';
    case Income = 'income';
    case Fee = 'fee';
    case Interest = 'interest';
    case Refund = 'refund';
    case CardPayment = 'card_payment';
    case LoanPayment = 'loan_payment';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
