<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Money\Enums\MoneyCashflowKind;
use App\Domain\Yoyu\Money\Enums\MoneyCertainty;
use App\Domain\Yoyu\Money\Enums\MoneyCostBehavior;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyFlexibility;
use App\Domain\Yoyu\Money\Enums\MoneyIncomeAmountBasis;
use App\Domain\Yoyu\Money\Enums\MoneyPaymentMethod;
use App\Domain\Yoyu\Money\Enums\MoneyPriority;
use App\Domain\Yoyu\Money\Enums\MoneyRecurringFrequency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'direction',
    'cashflow_kind',
    'amount_minor',
    'currency_code',
    'frequency',
    'interval_count',
    'day_of_month',
    'day_of_week',
    'month_of_year',
    'start_on',
    'end_on',
    'timezone',
    'category_id',
    'counterparty_id',
    'settlement_account_id',
    'credit_card_id',
    'loan_id',
    'payment_method',
    'income_amount_basis',
    'cost_behavior',
    'certainty',
    'flexibility',
    'priority',
    'is_active',
    'generated_through',
])]
class MoneyRecurringRule extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_recurring_rules';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency_code' => 'JPY',
        'interval_count' => 1,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MoneyDirection::class,
            'cashflow_kind' => MoneyCashflowKind::class,
            'amount_minor' => 'integer',
            'frequency' => MoneyRecurringFrequency::class,
            'interval_count' => 'integer',
            'day_of_month' => 'integer',
            'day_of_week' => 'integer',
            'month_of_year' => 'integer',
            'start_on' => 'date',
            'end_on' => 'date',
            'payment_method' => MoneyPaymentMethod::class,
            'income_amount_basis' => MoneyIncomeAmountBasis::class,
            'cost_behavior' => MoneyCostBehavior::class,
            'certainty' => MoneyCertainty::class,
            'flexibility' => MoneyFlexibility::class,
            'priority' => MoneyPriority::class,
            'is_active' => 'boolean',
            'generated_through' => 'date',
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
     * @return HasMany<MoneyCashflow, $this>
     */
    public function cashflows(): HasMany
    {
        return $this->hasMany(MoneyCashflow::class, 'recurring_rule_id');
    }
}
