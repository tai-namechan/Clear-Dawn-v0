<?php

namespace Database\Factories;

use App\Enums\DayAssignmentMode;
use App\Enums\DayPriorityTier;
use App\Models\ProgramDayTemplate;
use App\Models\ProgramVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramDayTemplate>
 */
class ProgramDayTemplateFactory extends Factory
{
    protected $model = ProgramDayTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_version_id' => ProgramVersion::factory(),
            'code' => 'DAY'.fake()->unique()->numberBetween(1, 10000),
            'name' => fake()->words(2, true),
            'priority_tier' => DayPriorityTier::Keep,
            'assignment_mode' => DayAssignmentMode::WeekdayFixed,
            'fixed_weekday' => fake()->numberBetween(1, 7),
            'is_optional' => false,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }

    public function sequential(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_mode' => DayAssignmentMode::Sequential,
            'fixed_weekday' => null,
        ]);
    }
}
