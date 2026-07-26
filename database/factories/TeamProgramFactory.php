<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeamProgram> */
class TeamProgramFactory extends Factory
{
    protected $model = TeamProgram::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'title' => fake()->words(3, true),
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addWeeks(4)->toDateString(),
            'visibility_status' => 'published',
            'summary' => fake()->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['visibility_status' => 'draft']);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['visibility_status' => 'archived']);
    }
}
