<?php

namespace App\Domain\Yoyu\Money\Enums;

enum MoneyLoanType: string
{
    case CardLoan = 'card_loan';
    case PersonalLoan = 'personal_loan';
    case MedicalLoan = 'medical_loan';
    case ShoppingLoan = 'shopping_loan';
    case AutoLoan = 'auto_loan';
    case StudentLoan = 'student_loan';
    case Mortgage = 'mortgage';
    case PayLater = 'pay_later';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
