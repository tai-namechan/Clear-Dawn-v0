<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Money\Enums\MoneyCardStatementStatus;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'credit_card_id',
    'period_start',
    'period_end',
    'closed_on',
    'due_on',
    'amount_minor',
    'status',
    'revision',
    'cashflow_id',
    'source',
    'supersedes_id',
    'paid_at',
])]
class MoneyCardStatement extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_card_statements';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'revision' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'closed_on' => 'date',
            'due_on' => 'date',
            'amount_minor' => 'integer',
            'status' => MoneyCardStatementStatus::class,
            'revision' => 'integer',
            'source' => MoneyTransactionSource::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MoneyCreditCard, $this>
     */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(MoneyCreditCard::class);
    }

    /**
     * @return BelongsTo<MoneyCashflow, $this>
     */
    public function cashflow(): BelongsTo
    {
        return $this->belongsTo(MoneyCashflow::class);
    }

    /**
     * @return BelongsTo<MoneyCardStatement, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(MoneyCardStatement::class, 'supersedes_id');
    }

    /**
     * @return HasMany<MoneyTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(MoneyTransaction::class, 'card_statement_id');
    }
}
