<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Domain\Yoyu\Money\Enums\MoneyLoanType;
use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoneyLoanRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(MoneyLoanType::values())],
            'lender_counterparty_id' => ['nullable', 'string', $this->ownedExists('yoyu_money_counterparties')],
            'currency_code' => $this->currencyCodeRules(false),
            'original_principal_minor' => $this->nonNegativeMinorRules(false),
            'outstanding_principal_minor' => $this->nonNegativeMinorRules(true),
            'annual_interest_rate_bps' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'monthly_payment_minor' => $this->nonNegativeMinorRules(true),
            'minimum_payment_minor' => $this->nonNegativeMinorRules(false),
            'next_payment_on' => ['required', 'date'],
            'maturity_on' => ['nullable', 'date'],
            'prepayment_allowed' => ['nullable', 'boolean'],
            'payment_account_id' => ['nullable', 'string', $this->ownedExists('yoyu_money_accounts')],
            'memo' => ['nullable', 'string', 'max:2000'],
            'balance_as_of' => ['nullable', 'date'],
        ];
    }
}
