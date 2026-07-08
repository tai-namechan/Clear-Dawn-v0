<?php

namespace App\Models;

use Database\Factories\TrainingSetLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $training_run_step_id
 * @property int $set_number
 * @property string|null $weight_kg
 * @property int|null $reps
 * @property string|null $distance_m
 * @property int|null $duration_seconds
 * @property string|null $memo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'training_run_step_id',
    'set_number',
    'weight_kg',
    'reps',
    'distance_m',
    'duration_seconds',
    'memo',
])]
class TrainingSetLog extends Model
{
    /** @use HasFactory<TrainingSetLogFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'distance_m' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<TrainingRunStep, $this>
     */
    public function trainingRunStep(): BelongsTo
    {
        return $this->belongsTo(TrainingRunStep::class);
    }
}
