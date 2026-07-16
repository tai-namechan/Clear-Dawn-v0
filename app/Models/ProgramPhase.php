<?php

namespace App\Models;

use App\Enums\PhaseIntent;
use Database\Factories\ProgramPhaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $program_version_id
 * @property string $name
 * @property PhaseIntent $intent
 * @property int $sort_order
 * @property string|null $progression_conditions
 */
#[Fillable([
    'program_version_id',
    'name',
    'intent',
    'sort_order',
    'progression_conditions',
])]
class ProgramPhase extends Model
{
    /** @use HasFactory<ProgramPhaseFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'intent' => PhaseIntent::class,
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
     * @return HasMany<ProgramWeek, $this>
     */
    public function weeks(): HasMany
    {
        return $this->hasMany(ProgramWeek::class)->orderBy('week_number');
    }
}
