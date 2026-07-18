<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyRecurringFrequency: string
{
    case Monthly = 'monthly';
    case Weekly = 'weekly';
    case Yearly = 'yearly';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
