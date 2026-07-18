<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Money\Enums\MoneyLoanStatus;
use App\Domain\Yoyu\Money\Enums\MoneyLoanType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'type',
    'lender_counterparty_id',
    'currency_code',
    'original_principal_minor',
    'outstanding_principal_minor',
    'annual_interest_rate_bps',
    'monthly_payment_minor',
    'minimum_payment_minor',
    'next_payment_on',
    'maturity_on',
    'prepayment_allowed',
    'payment_account_id',
    'status',
    'memo',
    'balance_as_of',
    'lock_version',
])]
class MoneyLoan extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_loans';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency_code' => 'JPY',
        'prepayment_allowed' => true,
        'lock_version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MoneyLoanType::class,
            'original_principal_minor' => 'integer',
            'outstanding_principal_minor' => 'integer',
            'annual_interest_rate_bps' => 'integer',
            'monthly_payment_minor' => 'integer',
            'minimum_payment_minor' => 'integer',
            'next_payment_on' => 'date',
            'maturity_on' => 'date',
            'prepayment_allowed' => 'boolean',
            'status' => MoneyLoanStatus::class,
            'balance_as_of' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MoneyCounterparty, $this>
     */
    public function lenderCounterparty(): BelongsTo
    {
        return $this->belongsTo(MoneyCounterparty::class, 'lender_counterparty_id');
    }

    /**
     * @return BelongsTo<MoneyAccount, $this>
     */
    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class, 'payment_account_id');
    }

    /**
     * @return HasMany<MoneyLoanPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(MoneyLoanPayment::class, 'loan_id');
    }

    /**
     * @return HasMany<MoneyCashflow, $this>
     */
    public function cashflows(): HasMany
    {
        return $this->hasMany(MoneyCashflow::class, 'loan_id');
    }

    /**
     * @return HasMany<MoneyTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(MoneyTransaction::class, 'loan_id');
    }
}
