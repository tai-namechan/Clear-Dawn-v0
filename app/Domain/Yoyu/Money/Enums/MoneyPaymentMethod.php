<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyPaymentMethod: string
{
    case Cash = 'cash';
    case AccountTransfer = 'account_transfer';
    case DirectDebit = 'direct_debit';
    case Card = 'card';
    case DebitCard = 'debit_card';
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
