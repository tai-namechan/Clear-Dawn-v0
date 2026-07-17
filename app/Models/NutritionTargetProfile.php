<?php

namespace App\Models;

use Database\Factories\NutritionTargetProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $program_version_id
 * @property string|null $program_phase_id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string $kcal
 * @property string $protein_g
 * @property string $fat_g
 * @property string $carb_g
 * @property string|null $note
 */
#[Fillable([
    'user_id',
    'program_version_id',
    'program_phase_id',
    'name',
    'starts_on',
    'ends_on',
    'kcal',
    'protein_g',
    'fat_g',
    'carb_g',
    'note',
])]
class NutritionTargetProfile extends Model
{
    /** @use HasFactory<NutritionTargetProfileFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'kcal' => 'decimal:2',
            'protein_g' => 'decimal:2',
            'fat_g' => 'decimal:2',
            'carb_g' => 'decimal:2',
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
     * @return BelongsTo<ProgramVersion, $this>
     */
    public function programVersion(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class);
    }

    /**
     * @return BelongsTo<ProgramPhase, $this>
     */
    public function programPhase(): BelongsTo
    {
        return $this->belongsTo(ProgramPhase::class);
    }
}
