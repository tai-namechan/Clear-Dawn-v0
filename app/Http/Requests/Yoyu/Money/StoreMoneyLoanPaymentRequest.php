<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreMoneyLoanPaymentRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'due_on' => ['required', 'date'],
            'total_minor' => $this->nonNegativeMinorRules(true),
            'principal_minor' => $this->nonNegativeMinorRules(false),
            'interest_minor' => $this->nonNegativeMinorRules(false),
            'fee_minor' => $this->nonNegativeMinorRules(false),
            'create_cashflow' => ['nullable', 'boolean'],
            'create_transaction' => ['nullable', 'boolean'],
            'lock_version' => ['required', 'integer'],
        ];
    }
}
