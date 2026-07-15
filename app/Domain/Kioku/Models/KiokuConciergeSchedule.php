<?php

namespace App\Domain\Kioku\Models;

use App\Domain\Kioku\KiokuConciergeScheduleState;
use App\Domain\Shared\Models\BelongsToUser;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\Domain\Kioku\KiokuConciergeScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-user daily pilot schedule
 * (docs/product/kioku-concierge-daily-pilot.md).
 *
 * @property string $id
 * @property int $user_id
 * @property string $state
 * @property Carbon|null $pilot_start_date
 * @property Carbon|null $pilot_end_date
 * @property int $pilot_days
 * @property string $timezone
 * @property string $daily_delivery_time
 * @property Carbon|null $next_delivery_at
 * @property int $consecutive_unopened
 * @property string|null $pause_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'state',
    'pilot_start_date',
    'pilot_end_date',
    'pilot_days',
    'timezone',
    'daily_delivery_time',
    'next_delivery_at',
    'consecutive_unopened',
    'pause_reason',
])]
class KiokuConciergeSchedule extends Model
{
    /** @use HasFactory<KiokuConciergeScheduleFactory> */
    use BelongsToUser, HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'state' => 'inactive',
        'pilot_days' => 14,
        'timezone' => 'Asia/Tokyo',
        'daily_delivery_time' => '21:00',
        'consecutive_unopened' => 0,
    ];

    protected static function newFactory(): KiokuConciergeScheduleFactory
    {
        return KiokuConciergeScheduleFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pilot_start_date' => 'date',
            'pilot_end_date' => 'date',
            'pilot_days' => 'integer',
            'next_delivery_at' => 'datetime',
            'consecutive_unopened' => 'integer',
        ];
    }

    public function stateEnum(): KiokuConciergeScheduleState
    {
        return KiokuConciergeScheduleState::from($this->state);
    }

    public function transitionTo(KiokuConciergeScheduleState $state, ?string $reason = null): void
    {
        $this->forceFill([
            'state' => $state->value,
            'pause_reason' => $reason,
        ])->save();
    }

    public function isActive(): bool
    {
        return $this->stateEnum() === KiokuConciergeScheduleState::Active;
    }

    public function isWithinPilot(CarbonInterface $localDate): bool
    {
        if ($this->pilot_start_date === null || $this->pilot_end_date === null) {
            return false;
        }

        $day = $localDate->toDateString();

        return $day >= $this->pilot_start_date->toDateString()
            && $day <= $this->pilot_end_date->toDateString();
    }

    public function pilotDayFor(CarbonInterface $localDate): ?int
    {
        if ($this->pilot_start_date === null || ! $this->isWithinPilot($localDate)) {
            return null;
        }

        $day = CarbonImmutable::instance($localDate)->startOfDay();
        $start = CarbonImmutable::parse($this->pilot_start_date->toDateString())->startOfDay();

        return ((int) $start->diffInDays($day)) + 1;
    }
}
