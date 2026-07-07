<?php

namespace App\Models;

use App\Enums\StepPurpose;
use Database\Factories\RoutineStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $routine_id
 * @property string $exercise_id
 * @property string|null $video_id
 * @property StepPurpose|null $purpose
 * @property int $sort_order
 * @property int|null $target_sets
 * @property int|null $target_reps
 * @property string|null $target_weight_kg
 * @property string|null $target_distance_m
 * @property int|null $target_duration_seconds
 * @property int|null $rest_seconds
 * @property string|null $note
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'routine_id',
    'exercise_id',
    'video_id',
    'purpose',
    'sort_order',
    'target_sets',
    'target_reps',
    'target_weight_kg',
    'target_distance_m',
    'target_duration_seconds',
    'rest_seconds',
    'note',
])]
class RoutineStep extends Model
{
    /** @use HasFactory<RoutineStepFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => StepPurpose::class,
            'target_weight_kg' => 'decimal:2',
            'target_distance_m' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Routine, $this>
     */
    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    /**
     * @return BelongsTo<Exercise, $this>
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
