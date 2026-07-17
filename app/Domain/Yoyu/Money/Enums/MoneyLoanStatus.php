<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyLoanStatus: string
{
    case Active = 'active';
    case PaidOff = 'paid_off';
    case Paused = 'paused';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
