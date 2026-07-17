<?php

namespace App\Models;

use Database\Factories\DailyCheckinFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property Carbon $checked_on
 * @property int|null $sleep_quality
 * @property int|null $fatigue
 * @property int|null $muscle_soreness
 * @property int|null $stress
 * @property int|null $mood
 * @property array<string, mixed>|null $region_tension
 * @property int|null $readiness_self
 * @property string|null $note
 */
#[Fillable([
    'user_id',
    'checked_on',
    'sleep_quality',
    'fatigue',
    'muscle_soreness',
    'stress',
    'mood',
    'region_tension',
    'readiness_self',
    'note',
])]
class DailyCheckin extends Model
{
    /** @use HasFactory<DailyCheckinFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_on' => 'date',
            'region_tension' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
