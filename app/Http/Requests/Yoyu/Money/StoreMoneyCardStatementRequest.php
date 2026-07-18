<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Domain\Yoyu\Money\Enums\MoneyCardStatementStatus;
use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoneyCardStatementRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'due_on' => ['required', 'date'],
            'amount_minor' => $this->nonNegativeMinorRules(true),
            'closed_on' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(MoneyCardStatementStatus::values())],
        ];
    }
}
