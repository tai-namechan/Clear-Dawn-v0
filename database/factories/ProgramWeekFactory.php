<?php

namespace Database\Factories;

use App\Models\ProgramPhase;
use App\Models\ProgramWeek;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramWeek>
 */
class ProgramWeekFactory extends Factory
{
    protected $model = ProgramWeek::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_version_id' => fn (array $attributes) => ProgramPhase::query()
                ->findOrFail($attributes['program_phase_id'])->program_version_id,
            'program_phase_id' => ProgramPhase::factory(),
            'week_number' => 1,
            'starts_on' => '2026-07-16',
            'intent' => null,
        ];
    }
}
