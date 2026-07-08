<?php

namespace App\Models;

use App\Enums\TrainingRunStatus;
use Database\Factories\TrainingRunFactory;
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
 * @property string $training_plan_id
 * @property TrainingRunStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'training_plan_id',
    'status',
    'started_at',
    'finished_at',
    'note',
])]
class TrainingRun extends Model
{
    /** @use HasFactory<TrainingRunFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TrainingRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
     * @return BelongsTo<TrainingPlan, $this>
     */
    public function trainingPlan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class);
    }

    /**
     * @return HasMany<TrainingRunStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(TrainingRunStep::class)->orderBy('sort_order');
    }
}
