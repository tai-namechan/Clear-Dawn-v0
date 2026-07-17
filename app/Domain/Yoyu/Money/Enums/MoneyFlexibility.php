<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyFlexibility: string
{
    case Required = 'required';
    case Adjustable = 'adjustable';
    case Stoppable = 'stoppable';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
