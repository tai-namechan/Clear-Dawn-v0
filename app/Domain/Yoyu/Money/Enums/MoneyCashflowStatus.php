<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyCashflowStatus: string
{
    case Planned = 'planned';
    case Confirmed = 'confirmed';
    case PartiallySettled = 'partially_settled';
    case Settled = 'settled';
    case Deferred = 'deferred';
    case Canceled = 'canceled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
