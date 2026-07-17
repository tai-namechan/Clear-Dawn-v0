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
    'account_id',
    'balance_minor',
    'available_balance_minor',
    'observed_at',
    'source',
    'note',
])]
class MoneyAccountBalanceSnapshot extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_account_balance_snapshots';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance_minor' => 'integer',
            'available_balance_minor' => 'integer',
            'observed_at' => 'datetime',
            'source' => MoneyTransactionSource::class,
        ];
    }

    /**
     * @return BelongsTo<MoneyAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class);
    }
}
