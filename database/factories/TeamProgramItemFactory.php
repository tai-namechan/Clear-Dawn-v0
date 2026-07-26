<?php

namespace Database\Factories;

use App\Models\TeamProgram;
use App\Models\TeamProgramItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeamProgramItem> */
class TeamProgramItemFactory extends Factory
{
    protected $model = TeamProgramItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_program_id' => TeamProgram::factory(),
            'title' => fake()->words(2, true),
            'sort_order' => 0,
        ];
    }
}
