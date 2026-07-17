<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyCardStatementStatus: string
{
    case Projected = 'projected';
    case Open = 'open';
    case Closed = 'closed';
    case Paid = 'paid';
    case Superseded = 'superseded';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
