<?php

namespace App\Http\Requests\Yoyu\Money;

use App\Domain\Yoyu\Money\Enums\MoneyCashflowKind;
use App\Domain\Yoyu\Money\Enums\MoneyCertainty;
use App\Domain\Yoyu\Money\Enums\MoneyCostBehavior;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyFlexibility;
use App\Domain\Yoyu\Money\Enums\MoneyIncomeAmountBasis;
use App\Domain\Yoyu\Money\Enums\MoneyPaymentMethod;
use App\Domain\Yoyu\Money\Enums\MoneyPriority;
use App\Http\Requests\Yoyu\Money\Concerns\AuthorizesMoneyUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMoneyCashflowRequest extends FormRequest
{
    use AuthorizesMoneyUser;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', 'string', Rule::in(MoneyDirection::values())],
            'kind' => ['required', 'string', Rule::in(MoneyCashflowKind::values())],
            'name' => ['required', 'string', 'max:255'],
            'amount_minor' => $this->nonNegativeMinorRules(true),
            'currency_code' => $this->currencyCodeRules(false),
            'due_on' => ['required', 'date'],
            'original_due_on' => ['nullable', 'date'],
            'certainty' => ['nullable', 'string', Rule::in(MoneyCertainty::values())],
            'category_id' => ['nullable', 'string', $this->ownedExists('yoyu_money_categories')],
            'counterparty_id' => ['nullable', 'string', $this->ownedExists('yoyu_money_counterparties')],
            'settlement_account_id' => ['nullable', 'string', $this->ownedExists('yoyu_money_accounts')],
            'credit_card_id' => ['nullable', 'string', $this->ownedExists('yoyu_money_credit_cards')],
            'loan_id' => ['nullable', 'string', $this->ownedExists('yoyu_money_loans')],
            'payment_method' => ['nullable', 'string', Rule::in(MoneyPaymentMethod::values())],
            'income_amount_basis' => ['nullable', 'string', Rule::in(MoneyIncomeAmountBasis::values())],
            'cost_behavior' => ['nullable', 'string', Rule::in(MoneyCostBehavior::values())],
            'flexibility' => ['nullable', 'string', Rule::in(MoneyFlexibility::values())],
            'priority' => ['nullable', 'string', Rule::in(MoneyPriority::values())],
            'memo' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
