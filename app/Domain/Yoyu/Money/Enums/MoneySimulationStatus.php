<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneySimulationStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case Stale = 'stale';
    case Applied = 'applied';
    case Discarded = 'discarded';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
