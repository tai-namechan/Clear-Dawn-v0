<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;

class SettleMoneyCashflowRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount_minor' => $this->nonNegativeMinorRules(true),
            'occurred_on' => ['required', 'date'],
            'lock_version' => ['required', 'integer'],
            'account_id' => ['nullable', 'string', $this->ownedExists('yoyu_money_accounts')],
            'create_transaction' => ['nullable', 'boolean'],
            'update_balance' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
