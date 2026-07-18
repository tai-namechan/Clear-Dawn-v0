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
    'loan_id',
    'due_on',
    'cashflow_id',
    'transaction_id',
    'total_minor',
    'principal_minor',
    'interest_minor',
    'fee_minor',
    'balance_after_minor',
    'status',
    'source',
])]
class MoneyLoanPayment extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_loan_payments';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'total_minor' => 'integer',
            'principal_minor' => 'integer',
            'interest_minor' => 'integer',
            'fee_minor' => 'integer',
            'balance_after_minor' => 'integer',
            'source' => MoneyTransactionSource::class,
        ];
    }

    /**
     * @return BelongsTo<MoneyLoan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(MoneyLoan::class);
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
