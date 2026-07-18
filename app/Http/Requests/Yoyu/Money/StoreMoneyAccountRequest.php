<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Domain\Yoyu\Money\Enums\MoneyAccountType;
use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoneyAccountRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(MoneyAccountType::values())],
            'currency_code' => $this->currencyCodeRules(false),
            'current_balance_minor' => $this->signedMinorRules(false),
            'available_balance_minor' => $this->signedMinorRules(false),
            'balance_as_of' => ['nullable', 'date'],
            'identifier_last4' => ['nullable', 'string', 'max:4'],
            'memo' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
