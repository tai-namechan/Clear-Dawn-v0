<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Money\Enums\MoneyCashflowKind;
use App\Domain\Yoyu\Money\Enums\MoneyCashflowStatus;
use App\Domain\Yoyu\Money\Enums\MoneyCertainty;
use App\Domain\Yoyu\Money\Enums\MoneyCostBehavior;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyFlexibility;
use App\Domain\Yoyu\Money\Enums\MoneyIncomeAmountBasis;
use App\Domain\Yoyu\Money\Enums\MoneyPaymentMethod;
use App\Domain\Yoyu\Money\Enums\MoneyPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'direction',
    'kind',
    'name',
    'amount_minor',
    'currency_code',
    'due_on',
    'original_due_on',
    'status',
    'certainty',
    'category_id',
    'counterparty_id',
    'settlement_account_id',
    'credit_card_id',
    'loan_id',
    'payment_method',
    'income_amount_basis',
    'cost_behavior',
    'recurring_rule_id',
    'occurrence_on',
    'supersedes_id',
    'flexibility',
    'priority',
    'memo',
    'settled_at',
    'lock_version',
])]
class MoneyCashflow extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_cashflows';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency_code' => 'JPY',
        'lock_version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MoneyDirection::class,
            'kind' => MoneyCashflowKind::class,
            'amount_minor' => 'integer',
            'due_on' => 'date',
            'original_due_on' => 'date',
            'status' => MoneyCashflowStatus::class,
            'certainty' => MoneyCertainty::class,
            'payment_method' => MoneyPaymentMethod::class,
            'income_amount_basis' => MoneyIncomeAmountBasis::class,
            'cost_behavior' => MoneyCostBehavior::class,
            'occurrence_on' => 'date',
            'flexibility' => MoneyFlexibility::class,
            'priority' => MoneyPriority::class,
            'settled_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MoneyCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MoneyCategory::class);
    }

    /**
     * @return BelongsTo<MoneyCounterparty, $this>
     */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(MoneyCounterparty::class);
    }

    /**
     * @return BelongsTo<MoneyAccount, $this>
     */
    public function settlementAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class, 'settlement_account_id');
    }

    /**
     * @return BelongsTo<MoneyCreditCard, $this>
     */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(MoneyCreditCard::class);
    }

    /**
     * @return BelongsTo<MoneyLoan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(MoneyLoan::class);
    }

    /**
     * @return BelongsTo<MoneyRecurringRule, $this>
     */
    public function recurringRule(): BelongsTo
    {
        return $this->belongsTo(MoneyRecurringRule::class);
    }

    /**
     * @return BelongsTo<MoneyCashflow, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(MoneyCashflow::class, 'supersedes_id');
    }

    /**
     * @return HasMany<MoneyReconciliation, $this>
     */
    public function reconciliations(): HasMany
    {
        return $this->hasMany(MoneyReconciliation::class, 'cashflow_id');
    }
}
