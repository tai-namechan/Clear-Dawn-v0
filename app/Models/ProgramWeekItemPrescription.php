<?php

namespace App\Models;

use Database\Factories\ProgramWeekItemPrescriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 週×種目処方の上書き（W1-W11 のメインリフト重量表の実体）。
 *
 * @property string $id
 * @property string $program_week_id
 * @property string $program_step_item_id
 * @property string|null $percent_of_reference
 * @property string|null $fixed_load
 * @property int|null $sets
 * @property int|null $reps
 * @property string|null $rpe_target
 * @property bool $is_test
 * @property string|null $intent
 * @property string|null $note
 */
#[Fillable([
    'program_week_id',
    'program_step_item_id',
    'percent_of_reference',
    'fixed_load',
    'sets',
    'reps',
    'rpe_target',
    'is_test',
    'intent',
    'note',
])]
class ProgramWeekItemPrescription extends Model
{
    /** @use HasFactory<ProgramWeekItemPrescriptionFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percent_of_reference' => 'decimal:4',
            'fixed_load' => 'decimal:2',
            'rpe_target' => 'decimal:1',
            'is_test' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProgramWeek, $this>
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(ProgramWeek::class, 'program_week_id');
    }

    /**
     * @return BelongsTo<ProgramStepItem, $this>
     */
    public function stepItem(): BelongsTo
    {
        return $this->belongsTo(ProgramStepItem::class, 'program_step_item_id');
    }
}
