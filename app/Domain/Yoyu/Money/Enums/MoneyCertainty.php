<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyCertainty: string
{
    case Confirmed = 'confirmed';
    case Expected = 'expected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
