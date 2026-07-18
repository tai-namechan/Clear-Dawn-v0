<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMoneyAccountBalanceRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_balance_minor' => $this->signedMinorRules(true),
            'available_balance_minor' => $this->signedMinorRules(false),
            'note' => ['nullable', 'string', 'max:2000'],
            'lock_version' => ['required', 'integer'],
        ];
    }
}
