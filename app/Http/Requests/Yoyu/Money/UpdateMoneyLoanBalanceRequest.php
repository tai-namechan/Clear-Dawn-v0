<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMoneyLoanBalanceRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'outstanding_principal_minor' => $this->nonNegativeMinorRules(true),
            'note' => ['nullable', 'string', 'max:2000'],
            'lock_version' => ['required', 'integer'],
        ];
    }
}
