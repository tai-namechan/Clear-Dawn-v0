<?php

namespace App\Models;

use Database\Factories\MatrixCellItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $matrix_cell_id
 * @property string $title
 * @property string|null $memo
 * @property bool $is_completed
 * @property Carbon|null $completed_at
 * @property int $sort_order
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['matrix_cell_id', 'title', 'memo', 'is_completed', 'completed_at', 'sort_order'])]
class MatrixCellItem extends Model
{
    /** @use HasFactory<MatrixCellItemFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MatrixCell, $this>
     */
    public function matrixCell(): BelongsTo
    {
        return $this->belongsTo(MatrixCell::class);
    }
}
