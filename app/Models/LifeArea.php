<?php

namespace App\Models;

use App\Enums\LifeAreaColor;
use Database\Factories\LifeAreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property LifeAreaColor $color
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'color', 'sort_order', 'is_active'])]
class LifeArea extends Model
{
    /** @use HasFactory<LifeAreaFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => LifeAreaColor::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<MatrixCell, $this>
     */
    public function matrixCells(): HasMany
    {
        return $this->hasMany(MatrixCell::class);
    }
}
