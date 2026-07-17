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
    'cashflow_id',
    'transaction_id',
    'amount_minor',
    'reconciled_at',
    'source',
    'note',
])]
class MoneyReconciliation extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_reconciliations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'reconciled_at' => 'datetime',
            'source' => MoneyTransactionSource::class,
        ];
    }

    /**
     * @return BelongsTo<MoneyCashflow, $this>
     */
    public function cashflow(): BelongsTo
    {
        return $this->belongsTo(MoneyCashflow::class);
    }

    /**
     * @return BelongsTo<MoneyTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(MoneyTransaction::class);
    }
}
