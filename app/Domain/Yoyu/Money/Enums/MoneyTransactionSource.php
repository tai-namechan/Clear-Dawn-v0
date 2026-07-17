<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyTransactionSource: string
{
    case Manual = 'manual';
    case Csv = 'csv';
    case Api = 'api';
    case Email = 'email';
    case System = 'system';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
