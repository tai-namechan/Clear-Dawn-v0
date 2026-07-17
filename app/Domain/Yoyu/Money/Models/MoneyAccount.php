<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Money\Enums\MoneyAccountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'type',
    'currency_code',
    'current_balance_minor',
    'available_balance_minor',
    'balance_as_of',
    'identifier_last4',
    'memo',
    'is_active',
    'lock_version',
])]
class MoneyAccount extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_accounts';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency_code' => 'JPY',
        'current_balance_minor' => 0,
        'is_active' => true,
        'lock_version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MoneyAccountType::class,
            'current_balance_minor' => 'integer',
            'available_balance_minor' => 'integer',
            'balance_as_of' => 'datetime',
            'is_active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /**
     * @return HasMany<MoneyAccountBalanceSnapshot, $this>
     */
    public function balanceSnapshots(): HasMany
    {
        return $this->hasMany(MoneyAccountBalanceSnapshot::class, 'account_id');
    }

    /**
     * @return HasMany<MoneyTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(MoneyTransaction::class, 'account_id');
    }

    /**
     * @return HasMany<MoneyCashflow, $this>
     */
    public function cashflows(): HasMany
    {
        return $this->hasMany(MoneyCashflow::class, 'settlement_account_id');
    }

    /**
     * @return HasMany<MoneyRecurringRule, $this>
     */
    public function recurringRules(): HasMany
    {
        return $this->hasMany(MoneyRecurringRule::class, 'settlement_account_id');
    }
}
