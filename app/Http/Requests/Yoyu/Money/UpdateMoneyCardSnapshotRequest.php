<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMoneyCardSnapshotRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'limit_minor' => $this->nonNegativeMinorRules(false),
            'available_minor' => $this->nonNegativeMinorRules(false),
            'current_statement_minor' => $this->nonNegativeMinorRules(false),
            'unconfirmed_minor' => $this->nonNegativeMinorRules(false),
            'revolving_balance_minor' => $this->nonNegativeMinorRules(false),
            'installment_balance_minor' => $this->nonNegativeMinorRules(false),
            'minimum_payment_minor' => $this->nonNegativeMinorRules(false),
            'observed_at' => ['nullable', 'date'],
            'lock_version' => ['required', 'integer'],
        ];
    }
}
