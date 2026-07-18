<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyAccountType: string
{
    case Bank = 'bank';
    case Cash = 'cash';
    case ElectronicMoney = 'electronic_money';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
