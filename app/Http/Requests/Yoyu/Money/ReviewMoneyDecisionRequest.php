<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;

class ReviewMoneyDecisionRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'actual_effect_payload' => ['required', 'array'],
            'reflection' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
