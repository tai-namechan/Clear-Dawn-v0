<?php

namespace App\Models;

use App\Enums\ProgramStepKind;
use App\Enums\RequiredLevel;
use Database\Factories\ProgramDayStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DAY テンプレート内の順序付き STEP。program_choice_option_id が非 null の STEP は
 * 選択式メニューで当該オプションを選んだときのみプランに含まれる。
 *
 * @property string $id
 * @property string $program_day_template_id
 * @property string|null $program_choice_option_id
 * @property string $name
 * @property ProgramStepKind $step_kind
 * @property int $sort_order
 * @property RequiredLevel $required_level
 * @property string|null $purpose
 * @property int|null $estimated_minutes
 * @property string|null $start_condition
 * @property string|null $completion_condition
 * @property string|null $abort_condition
 * @property string|null $note
 */
#[Fillable([
    'program_day_template_id',
    'program_choice_option_id',
    'name',
    'step_kind',
    'sort_order',
    'required_level',
    'purpose',
    'estimated_minutes',
    'start_condition',
    'completion_condition',
    'abort_condition',
    'note',
])]
class ProgramDayStep extends Model
{
    /** @use HasFactory<ProgramDayStepFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_kind' => ProgramStepKind::class,
            'required_level' => RequiredLevel::class,
        ];
    }

    /**
     * @return BelongsTo<ProgramDayTemplate, $this>
     */
    public function dayTemplate(): BelongsTo
    {
        return $this->belongsTo(ProgramDayTemplate::class, 'program_day_template_id');
    }

    /**
     * @return BelongsTo<ProgramChoiceOption, $this>
     */
    public function choiceOption(): BelongsTo
    {
        return $this->belongsTo(ProgramChoiceOption::class, 'program_choice_option_id');
    }

    /**
     * @return HasMany<ProgramStepItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProgramStepItem::class)->orderBy('sort_order');
    }
}
