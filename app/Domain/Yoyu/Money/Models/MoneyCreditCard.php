<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'issuer_name',
    'identifier_last4',
    'currency_code',
    'closing_day',
    'payment_day',
    'payment_month_offset',
    'payment_account_id',
    'limit_minor',
    'available_minor',
    'current_statement_minor',
    'unconfirmed_minor',
    'revolving_balance_minor',
    'installment_balance_minor',
    'revolving_fee_rate_bps',
    'minimum_payment_minor',
    'default_payment_type',
    'snapshot_as_of',
    'is_active',
    'lock_version',
])]
class MoneyCreditCard extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_credit_cards';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency_code' => 'JPY',
        'payment_month_offset' => 1,
        'default_payment_type' => 'lump_sum',
        'is_active' => true,
        'lock_version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_month_offset' => 'integer',
            'limit_minor' => 'integer',
            'available_minor' => 'integer',
            'current_statement_minor' => 'integer',
            'unconfirmed_minor' => 'integer',
            'revolving_balance_minor' => 'integer',
            'installment_balance_minor' => 'integer',
            'revolving_fee_rate_bps' => 'integer',
            'minimum_payment_minor' => 'integer',
            'snapshot_as_of' => 'datetime',
            'is_active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MoneyAccount, $this>
     */
    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class, 'payment_account_id');
    }

    /**
     * @return HasMany<MoneyCardSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(MoneyCardSnapshot::class, 'credit_card_id');
    }

    /**
     * @return HasMany<MoneyCardStatement, $this>
     */
    public function statements(): HasMany
    {
        return $this->hasMany(MoneyCardStatement::class, 'credit_card_id');
    }

    /**
     * @return HasMany<MoneyCashflow, $this>
     */
    public function cashflows(): HasMany
    {
        return $this->hasMany(MoneyCashflow::class, 'credit_card_id');
    }

    /**
     * @return HasMany<MoneyTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(MoneyTransaction::class, 'credit_card_id');
    }
}
