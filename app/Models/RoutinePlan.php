<?php

namespace App\Models;

use App\Enums\RoutinePlanStatus;
use Database\Factories\RoutinePlanFactory;
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
 * @property string|null $program_version_id
 * @property string|null $program_week_id
 * @property string|null $program_day_template_id
 * @property string|null $generation_source
 * @property string|null $choice_option_id
 * @property string|null $choice_reason
 * @property string|null $repeat_reason
 * @property string|null $adjustment_reason
 * @property string $title
 * @property Carbon $scheduled_on
 * @property RoutinePlanStatus $status
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'life_area_id',
    'routine_id',
    'program_version_id',
    'program_week_id',
    'program_day_template_id',
    'generation_source',
    'choice_option_id',
    'choice_reason',
    'repeat_reason',
    'adjustment_reason',
    'title',
    'scheduled_on',
    'status',
    'note',
])]
class RoutinePlan extends Model
{
    /** @use HasFactory<RoutinePlanFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
            'status' => RoutinePlanStatus::class,
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
     * @return BelongsTo<ProgramVersion, $this>
     */
    public function programVersion(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class);
    }

    /**
     * @return BelongsTo<ProgramWeek, $this>
     */
    public function programWeek(): BelongsTo
    {
        return $this->belongsTo(ProgramWeek::class);
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
        return $this->belongsTo(ProgramChoiceOption::class, 'choice_option_id');
    }

    /**
     * @return HasMany<RoutinePlanStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(RoutinePlanStep::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<RoutineSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(RoutineSession::class)->orderByDesc('started_at');
    }
}
