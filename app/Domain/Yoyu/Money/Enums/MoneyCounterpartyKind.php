<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyCounterpartyKind: string
{
    case Merchant = 'merchant';
    case Employer = 'employer';
    case Lender = 'lender';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
