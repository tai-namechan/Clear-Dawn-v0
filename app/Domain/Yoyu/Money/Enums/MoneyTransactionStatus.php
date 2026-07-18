<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyTransactionStatus: string
{
    case Posted = 'posted';
    case Voided = 'voided';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
