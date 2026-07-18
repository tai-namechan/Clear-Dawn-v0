<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyCashflowKind: string
{
    case Income = 'income';
    case Expense = 'expense';
    case CardStatement = 'card_statement';
    case LoanPayment = 'loan_payment';
    case Transfer = 'transfer';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
