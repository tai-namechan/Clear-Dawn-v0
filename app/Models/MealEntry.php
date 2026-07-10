<?php

namespace App\Models;

use App\Enums\MealType;
use Database\Factories\MealEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $food_item_id
 * @property Carbon $eaten_on
 * @property MealType $meal_type
 * @property string $name
 * @property string $quantity
 * @property string $kcal
 * @property string $protein_g
 * @property string $fat_g
 * @property string $carb_g
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'food_item_id',
    'eaten_on',
    'meal_type',
    'name',
    'quantity',
    'kcal',
    'protein_g',
    'fat_g',
    'carb_g',
    'note',
])]
class MealEntry extends Model
{
    /** @use HasFactory<MealEntryFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'eaten_on' => 'date',
            'meal_type' => MealType::class,
            'quantity' => 'decimal:2',
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
     * @return BelongsTo<FoodItem, $this>
     */
    public function foodItem(): BelongsTo
    {
        return $this->belongsTo(FoodItem::class);
    }
}
