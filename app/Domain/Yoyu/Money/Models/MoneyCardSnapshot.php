<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'credit_card_id',
    'observed_at',
    'limit_minor',
    'available_minor',
    'current_statement_minor',
    'unconfirmed_minor',
    'revolving_balance_minor',
    'installment_balance_minor',
    'minimum_payment_minor',
    'source',
])]
class MoneyCardSnapshot extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_card_snapshots';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'limit_minor' => 'integer',
            'available_minor' => 'integer',
            'current_statement_minor' => 'integer',
            'unconfirmed_minor' => 'integer',
            'revolving_balance_minor' => 'integer',
            'installment_balance_minor' => 'integer',
            'minimum_payment_minor' => 'integer',
            'source' => MoneyTransactionSource::class,
        ];
    }

    /**
     * @return BelongsTo<MoneyCreditCard, $this>
     */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(MoneyCreditCard::class);
    }
}
