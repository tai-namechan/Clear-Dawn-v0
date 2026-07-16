<?php

namespace App\Models;

use Database\Factories\ProgramWeekFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $program_version_id
 * @property string $program_phase_id
 * @property int $week_number
 * @property Carbon $starts_on
 * @property string|null $intent
 */
#[Fillable([
    'program_version_id',
    'program_phase_id',
    'week_number',
    'starts_on',
    'intent',
])]
class ProgramWeek extends Model
{
    /** @use HasFactory<ProgramWeekFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
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
     * @return BelongsTo<ProgramPhase, $this>
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProgramPhase::class, 'program_phase_id');
    }

    /**
     * @return HasMany<ProgramWeekItemPrescription, $this>
     */
    public function itemPrescriptions(): HasMany
    {
        return $this->hasMany(ProgramWeekItemPrescription::class);
    }
}
