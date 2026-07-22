<?php

namespace App\Models;

use Database\Factories\FoodItemFactory;
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
 * @property string $serving_label
 * @property string $kcal
 * @property string $protein_g
 * @property string $fat_g
 * @property string $carb_g
 * @property string|null $source
 * @property string|null $barcode 正規化済みバーコード（PR-F1）
 * @property string|null $barcode_type
 * @property string|null $store_name
 * @property string|null $menu_name
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'name',
    'serving_label',
    'kcal',
    'protein_g',
    'fat_g',
    'carb_g',
    'source',
    'barcode',
    'barcode_type',
    'store_name',
    'menu_name',
])]
class FoodItem extends Model
{
    /** @use HasFactory<FoodItemFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kcal' => 'decimal:2',
            'protein_g' => 'decimal:2',
            'fat_g' => 'decimal:2',
            'carb_g' => 'decimal:2',
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
     * @return HasMany<MealEntry, $this>
     */
    public function mealEntries(): HasMany
    {
        return $this->hasMany(MealEntry::class);
    }
}
