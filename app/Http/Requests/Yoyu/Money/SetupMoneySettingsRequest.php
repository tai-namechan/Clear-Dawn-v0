<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;

class SetupMoneySettingsRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'timezone' => ['nullable', 'string', 'max:64'],
            'currency_code' => $this->currencyCodeRules(false),
            'minimum_living_budget_minor' => $this->nonNegativeMinorRules(false),
            'safety_buffer_minor' => $this->nonNegativeMinorRules(false),
            'uncertain_outflow_reserve_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'include_expected_income' => ['nullable', 'boolean'],
            'calculation_horizon_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'formula_version' => ['nullable', 'string', 'max:32'],
        ];
    }
}
