<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Money\Enums\MoneyCostBehavior;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyPaymentMethod;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionKind;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionSource;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'account_id',
    'direction',
    'kind',
    'amount_minor',
    'currency_code',
    'occurred_on',
    'posted_on',
    'description_raw',
    'description_normalized',
    'category_id',
    'counterparty_id',
    'credit_card_id',
    'loan_id',
    'card_statement_id',
    'payment_method',
    'card_payment_type',
    'cost_behavior',
    'status',
    'source',
    'source_provider',
    'external_id',
    'import_id',
    'import_row_id',
    'transfer_group_id',
    'memo',
    'edited_at',
    'voided_at',
    'void_reason',
])]
class MoneyTransaction extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_transactions';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency_code' => 'JPY',
        'status' => 'posted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MoneyDirection::class,
            'kind' => MoneyTransactionKind::class,
            'amount_minor' => 'integer',
            'occurred_on' => 'date',
            'posted_on' => 'date',
            'payment_method' => MoneyPaymentMethod::class,
            'cost_behavior' => MoneyCostBehavior::class,
            'status' => MoneyTransactionStatus::class,
            'source' => MoneyTransactionSource::class,
            'edited_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MoneyAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class);
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
     * @return BelongsTo<MoneyCardStatement, $this>
     */
    public function cardStatement(): BelongsTo
    {
        return $this->belongsTo(MoneyCardStatement::class);
    }

    /**
     * @return BelongsTo<MoneyImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(MoneyImport::class);
    }

    /**
     * @return BelongsTo<MoneyImportRow, $this>
     */
    public function importRow(): BelongsTo
    {
        return $this->belongsTo(MoneyImportRow::class);
    }

    /**
     * @return HasMany<MoneyReconciliation, $this>
     */
    public function reconciliations(): HasMany
    {
        return $this->hasMany(MoneyReconciliation::class, 'transaction_id');
    }
}
