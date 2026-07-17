<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Domain\Yoyu\Money\Enums\MoneyDecisionStatus;
use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoneyDecisionRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'decided_on' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(MoneyDecisionStatus::values())],
            'simulation_id' => ['nullable', 'string', $this->ownedExists('yoyu_money_simulations')],
            'before_payload' => ['nullable', 'array'],
            'expected_effect_payload' => ['nullable', 'array'],
            'memo' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
