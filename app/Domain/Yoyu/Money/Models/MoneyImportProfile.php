<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'source_format',
    'encoding',
    'delimiter',
    'has_header',
    'column_mapping',
    'transform_rules',
    'mapping_config',
    'default_account_id',
    'default_currency_code',
    'default_direction',
    'is_active',
])]
class MoneyImportProfile extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_import_profiles';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source_format' => 'csv',
        'has_header' => true,
        'default_currency_code' => 'JPY',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_header' => 'boolean',
            'column_mapping' => 'array',
            'transform_rules' => 'array',
            'mapping_config' => 'array',
            'default_direction' => MoneyDirection::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MoneyAccount, $this>
     */
    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class, 'default_account_id');
    }

    /**
     * @return HasMany<MoneyImport, $this>
     */
    public function imports(): HasMany
    {
        return $this->hasMany(MoneyImport::class, 'import_profile_id');
    }
}
