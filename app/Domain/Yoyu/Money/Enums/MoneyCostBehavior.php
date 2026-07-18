<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyCostBehavior: string
{
    case Fixed = 'fixed';
    case Variable = 'variable';
    case OneTime = 'one_time';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
