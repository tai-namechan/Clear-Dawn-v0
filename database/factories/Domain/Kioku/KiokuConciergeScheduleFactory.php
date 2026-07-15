<?php

namespace Database\Factories\Domain\Kioku;

use App\Domain\Kioku\KiokuConciergeScheduleState;
use App\Domain\Kioku\Models\KiokuConciergeSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KiokuConciergeSchedule>
 */
class KiokuConciergeScheduleFactory extends Factory
{
    protected $model = KiokuConciergeSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'state' => KiokuConciergeScheduleState::Inactive->value,
            'pilot_start_date' => null,
            'pilot_end_date' => null,
            'pilot_days' => 14,
            'timezone' => 'Asia/Tokyo',
            'daily_delivery_time' => '21:00',
            'next_delivery_at' => null,
            'consecutive_unopened' => 0,
            'pause_reason' => null,
        ];
    }

    public function active(string $start = '2026-07-15', int $days = 14): static
    {
        $startDate = CarbonImmutable::parse($start)->startOfDay();
        $endDate = $startDate->addDays($days - 1);

        return $this->state(fn () => [
            'state' => KiokuConciergeScheduleState::Active->value,
            'pilot_start_date' => $startDate->toDateString(),
            'pilot_end_date' => $endDate->toDateString(),
            'pilot_days' => $days,
            'next_delivery_at' => $startDate->setTime(21, 0),
        ]);
    }
}
