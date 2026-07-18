<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Money\Enums\MoneyImportRowStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'import_id',
    'row_number',
    'raw_payload',
    'normalized_payload',
    'status',
    'issue_codes',
    'transaction_id',
    'duplicate_of_transaction_id',
])]
class MoneyImportRow extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_import_rows';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'raw_payload' => 'array',
            'normalized_payload' => 'array',
            'status' => MoneyImportRowStatus::class,
            'issue_codes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<MoneyImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(MoneyImport::class);
    }

    /**
     * @return BelongsTo<MoneyTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(MoneyTransaction::class);
    }

    /**
     * @return BelongsTo<MoneyTransaction, $this>
     */
    public function duplicateOfTransaction(): BelongsTo
    {
        return $this->belongsTo(MoneyTransaction::class, 'duplicate_of_transaction_id');
    }
}
