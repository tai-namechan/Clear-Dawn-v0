<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Money\Enums\MoneyImportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'import_profile_id',
    'account_id',
    'status',
    'source_filename',
    'source_storage_path',
    'source_checksum',
    'idempotency_key',
    'mapping_config',
    'row_count',
    'accepted_count',
    'rejected_count',
    'duplicate_count',
    'started_at',
    'finished_at',
    'error_message',
])]
class MoneyImport extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_imports';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'row_count' => 0,
        'accepted_count' => 0,
        'rejected_count' => 0,
        'duplicate_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MoneyImportStatus::class,
            'mapping_config' => 'array',
            'row_count' => 'integer',
            'accepted_count' => 'integer',
            'rejected_count' => 'integer',
            'duplicate_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MoneyImportProfile, $this>
     */
    public function importProfile(): BelongsTo
    {
        return $this->belongsTo(MoneyImportProfile::class);
    }

    /**
     * @return BelongsTo<MoneyAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class);
    }

    /**
     * @return HasMany<MoneyImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(MoneyImportRow::class, 'import_id');
    }

    /**
     * @return HasMany<MoneyTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(MoneyTransaction::class, 'import_id');
    }
}
