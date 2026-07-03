<?php

namespace App\Models;

use Database\Factories\MatrixCellFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $life_area_id
 * @property string $matrix_row_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'life_area_id', 'matrix_row_id'])]
class MatrixCell extends Model
{
    /** @use HasFactory<MatrixCellFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<LifeArea, $this>
     */
    public function lifeArea(): BelongsTo
    {
        return $this->belongsTo(LifeArea::class);
    }

    /**
     * @return BelongsTo<MatrixRow, $this>
     */
    public function matrixRow(): BelongsTo
    {
        return $this->belongsTo(MatrixRow::class);
    }

    /**
     * @return HasMany<MatrixCellItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MatrixCellItem::class);
    }
}
