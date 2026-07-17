<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyIncomeAmountBasis: string
{
    case Net = 'net';
    case Gross = 'gross';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
