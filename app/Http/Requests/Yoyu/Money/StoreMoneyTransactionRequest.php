<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionKind;
use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoneyTransactionRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'string', $this->ownedExists('yoyu_money_accounts')],
            'direction' => ['required', 'string', Rule::in(MoneyDirection::values())],
            'kind' => ['required', 'string', Rule::in(MoneyTransactionKind::values())],
            'amount_minor' => $this->nonNegativeMinorRules(true),
            'currency_code' => $this->currencyCodeRules(false),
            'occurred_on' => ['required', 'date'],
            'posted_on' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'description_raw' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'string', $this->ownedExists('yoyu_money_categories')],
            'counterparty_id' => ['nullable', 'string', $this->ownedExists('yoyu_money_counterparties')],
            'credit_card_id' => ['nullable', 'string', $this->ownedExists('yoyu_money_credit_cards')],
            'loan_id' => ['nullable', 'string', $this->ownedExists('yoyu_money_loans')],
            'memo' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
