<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreMoneyCardRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'issuer_name' => ['nullable', 'string', 'max:255'],
            'identifier_last4' => ['nullable', 'string', 'max:4'],
            'currency_code' => $this->currencyCodeRules(false),
            'closing_day' => ['required', 'string', 'max:8'],
            'payment_day' => ['required', 'string', 'max:8'],
            'payment_month_offset' => ['nullable', 'integer', 'min:0', 'max:3'],
            'payment_account_id' => ['nullable', 'string', $this->ownedExists('yoyu_money_accounts')],
            'limit_minor' => $this->nonNegativeMinorRules(false),
            'available_minor' => $this->nonNegativeMinorRules(false),
            'current_statement_minor' => $this->nonNegativeMinorRules(false),
            'unconfirmed_minor' => $this->nonNegativeMinorRules(false),
            'revolving_balance_minor' => $this->nonNegativeMinorRules(false),
            'installment_balance_minor' => $this->nonNegativeMinorRules(false),
            'revolving_fee_rate_bps' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'minimum_payment_minor' => $this->nonNegativeMinorRules(false),
            'default_payment_type' => ['nullable', 'string', 'max:64'],
            'snapshot_as_of' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
