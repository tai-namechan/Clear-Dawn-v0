<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyDirection: string
{
    case Inflow = 'inflow';
    case Outflow = 'outflow';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
