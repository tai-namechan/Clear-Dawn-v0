<?php

namespace App\Models;

use Database\Factories\RoutineFactory;
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
 * @property string|null $life_area_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'life_area_id',
    'name',
    'description',
    'is_active',
    'sort_order',
])]
class Routine extends Model
{
    /** @use HasFactory<RoutineFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * @return BelongsTo<LifeArea, $this>
     */
    public function lifeArea(): BelongsTo
    {
        return $this->belongsTo(LifeArea::class);
    }

    /**
     * @return HasMany<RoutineStep, $this>
     */
    public function routineSteps(): HasMany
    {
        return $this->hasMany(RoutineStep::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<RoutinePlan, $this>
     */
    public function routinePlans(): HasMany
    {
        return $this->hasMany(RoutinePlan::class);
    }
}
