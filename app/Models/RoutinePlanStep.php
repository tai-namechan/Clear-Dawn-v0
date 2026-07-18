<?php

namespace App\Models;

use App\Enums\StepPurpose;
use Database\Factories\RoutinePlanStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $routine_plan_id
 * @property string $routine_item_id
 * @property string|null $title
 * @property string|null $video_id
 * @property StepPurpose|null $purpose
 * @property int $sort_order
 * @property string|null $target_load
 * @property string|null $load_unit
 * @property string|null $target_amount
 * @property string|null $amount_unit
 * @property int|null $target_blocks
 * @property int|null $rest_seconds
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'routine_plan_id',
    'routine_item_id',
    'program_step_item_id',
    'title',
    'video_id',
    'purpose',
    'step_kind',
    'required_level',
    'sort_order',
    'target_load',
    'load_unit',
    'target_amount',
    'amount_unit',
    'target_blocks',
    'rest_seconds',
    'note',
])]
class RoutinePlanStep extends Model
{
    /** @use HasFactory<RoutinePlanStepFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => StepPurpose::class,
            'target_load' => 'decimal:2',
            'target_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<RoutinePlan, $this>
     */
    public function routinePlan(): BelongsTo
    {
        return $this->belongsTo(RoutinePlan::class);
    }

    /**
     * @return BelongsTo<RoutineItem, $this>
     */
    public function routineItem(): BelongsTo
    {
        return $this->belongsTo(RoutineItem::class);
    }

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
