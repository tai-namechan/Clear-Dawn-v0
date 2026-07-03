<?php

namespace App\Models;

use App\Enums\MatrixRowKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * グローバルマスタ（user_id を持たない）。固定 3 行を seed で投入する。
 *
 * @property string $id
 * @property MatrixRowKey $key
 * @property string $label
 * @property int $sort_order
 * @property bool $is_checkable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'label', 'sort_order', 'is_checkable'])]
class MatrixRow extends Model
{
    use HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => MatrixRowKey::class,
            'is_checkable' => 'boolean',
        ];
    }

    /**
     * @return HasMany<MatrixCell, $this>
     */
    public function matrixCells(): HasMany
    {
        return $this->hasMany(MatrixCell::class);
    }
}
