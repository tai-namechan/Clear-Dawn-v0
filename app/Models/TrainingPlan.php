<?php

namespace App\Models;

use App\Enums\TrainingPlanStatus;
use Database\Factories\TrainingPlanFactory;
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
 * @property string|null $life_area_id
 * @property string|null $routine_id
 * @property string $title
 * @property Carbon $scheduled_on
 * @property TrainingPlanStatus $status
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'life_area_id',
    'routine_id',
    'title',
    'scheduled_on',
    'status',
    'note',
])]
class TrainingPlan extends Model
{
    /** @use HasFactory<TrainingPlanFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
            'status' => TrainingPlanStatus::class,
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
     * @return BelongsTo<Routine, $this>
     */
    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    /**
     * @return HasMany<TrainingPlanStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(TrainingPlanStep::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<TrainingRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(TrainingRun::class);
    }
}
