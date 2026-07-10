<?php

namespace App\Models;

use Database\Factories\NutritionGoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $kcal
 * @property string $protein_g
 * @property string $fat_g
 * @property string $carb_g
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'kcal',
    'protein_g',
    'fat_g',
    'carb_g',
])]
class NutritionGoal extends Model
{
    /** @use HasFactory<NutritionGoalFactory> */
    use HasFactory, HasUlids;

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
}
