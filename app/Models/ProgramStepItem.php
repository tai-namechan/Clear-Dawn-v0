<?php

namespace App\Models;

use App\Enums\ProgressionMode;
use App\Enums\RequiredLevel;
use Database\Factories\ProgramStepItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * STEP 内の種目処方。重量は percent_of_reference（基準リフト1RM比）で保持し、
 * 個人1RM（personal_profile_entries の reference_lift キー）から表示時に導出する。
 *
 * @property string $id
 * @property string $program_day_step_id
 * @property string $routine_item_id
 * @property int $sort_order
 * @property int|null $sets
 * @property int|null $reps
 * @property string|null $amount_value
 * @property string|null $amount_unit
 * @property string|null $fixed_load
 * @property string|null $load_unit
 * @property string|null $percent_of_reference
 * @property string|null $reference_lift
 * @property string|null $rpe_target
 * @property int|null $rest_seconds
 * @property string|null $side
 * @property string|null $tempo
 * @property string|null $cues
 * @property RequiredLevel $required_level
 * @property ProgressionMode $progression_mode
 * @property array<int, string>|null $alternates
 * @property string|null $abort_condition
 * @property string|null $completion_condition
 * @property string|null $note
 */
#[Fillable([
    'program_day_step_id',
    'routine_item_id',
    'sort_order',
    'sets',
    'reps',
    'amount_value',
    'amount_unit',
    'fixed_load',
    'load_unit',
    'percent_of_reference',
    'reference_lift',
    'rpe_target',
    'rest_seconds',
    'side',
    'tempo',
    'cues',
    'required_level',
    'progression_mode',
    'alternates',
    'abort_condition',
    'completion_condition',
    'note',
])]
class ProgramStepItem extends Model
{
    /** @use HasFactory<ProgramStepItemFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_value' => 'decimal:2',
            'fixed_load' => 'decimal:2',
            'percent_of_reference' => 'decimal:4',
            'rpe_target' => 'decimal:1',
            'required_level' => RequiredLevel::class,
            'progression_mode' => ProgressionMode::class,
            'alternates' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ProgramDayStep, $this>
     */
    public function dayStep(): BelongsTo
    {
        return $this->belongsTo(ProgramDayStep::class, 'program_day_step_id');
    }

    /**
     * @return BelongsTo<RoutineItem, $this>
     */
    public function routineItem(): BelongsTo
    {
        return $this->belongsTo(RoutineItem::class);
    }

    /**
     * @return HasMany<ProgramWeekItemPrescription, $this>
     */
    public function weekPrescriptions(): HasMany
    {
        return $this->hasMany(ProgramWeekItemPrescription::class);
    }
}
