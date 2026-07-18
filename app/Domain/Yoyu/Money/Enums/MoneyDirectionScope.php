<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyDirectionScope: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Both = 'both';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
