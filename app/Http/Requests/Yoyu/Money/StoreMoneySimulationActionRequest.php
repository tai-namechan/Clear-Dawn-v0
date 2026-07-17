<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Domain\Yoyu\Money\Enums\MoneySimulationActionType;
use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoneySimulationActionRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action_type' => ['required', 'string', Rule::in(MoneySimulationActionType::values())],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'target_type' => ['nullable', 'string', 'max:255'],
            'target_id' => ['nullable', 'string', 'max:26'],
            'params_payload' => ['nullable', 'array'],
        ];
    }
}
