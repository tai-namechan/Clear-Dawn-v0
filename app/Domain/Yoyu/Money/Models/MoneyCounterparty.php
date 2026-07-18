<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Money\Enums\MoneyCounterpartyKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'normalized_name',
    'kind',
    'memo',
    'is_active',
])]
class MoneyCounterparty extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_counterparties';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MoneyCounterpartyKind::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<MoneyCashflow, $this>
     */
    public function cashflows(): HasMany
    {
        return $this->hasMany(MoneyCashflow::class, 'counterparty_id');
    }

    /**
     * @return HasMany<MoneyTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(MoneyTransaction::class, 'counterparty_id');
    }

    /**
     * @return HasMany<MoneyLoan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(MoneyLoan::class, 'lender_counterparty_id');
    }
}
