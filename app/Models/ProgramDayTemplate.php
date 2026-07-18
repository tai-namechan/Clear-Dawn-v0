<?php

namespace App\Models;

use App\Enums\DayAssignmentMode;
use App\Enums\DayPriorityTier;
use Database\Factories\ProgramDayTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $program_version_id
 * @property string $code
 * @property string $name
 * @property DayPriorityTier $priority_tier
 * @property DayAssignmentMode $assignment_mode
 * @property int|null $fixed_weekday ISO-8601（1=月 .. 7=日）
 * @property int|null $estimated_minutes_min
 * @property int|null $estimated_minutes_max
 * @property bool $is_optional
 * @property bool $is_active
 * @property int $sort_order
 * @property string|null $note
 */
#[Fillable([
    'program_version_id',
    'code',
    'name',
    'priority_tier',
    'assignment_mode',
    'fixed_weekday',
    'estimated_minutes_min',
    'estimated_minutes_max',
    'is_optional',
    'is_active',
    'sort_order',
    'note',
])]
class ProgramDayTemplate extends Model
{
    /** @use HasFactory<ProgramDayTemplateFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority_tier' => DayPriorityTier::class,
            'assignment_mode' => DayAssignmentMode::class,
            'is_optional' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProgramVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class, 'program_version_id');
    }

    /**
     * @return HasMany<ProgramDayStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ProgramDayStep::class)->orderBy('sort_order');
    }

    /**
     * @return HasOne<ProgramChoiceGroup, $this>
     */
    public function choiceGroup(): HasOne
    {
        return $this->hasOne(ProgramChoiceGroup::class);
    }
}
