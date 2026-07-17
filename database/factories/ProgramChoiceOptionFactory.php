<?php

namespace Database\Factories;

use App\Models\ProgramChoiceGroup;
use App\Models\ProgramChoiceOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramChoiceOption>
 */
class ProgramChoiceOptionFactory extends Factory
{
    protected $model = ProgramChoiceOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_choice_group_id' => ProgramChoiceGroup::factory(),
            'label' => fake()->words(2, true),
            'description' => null,
            'estimated_minutes' => fake()->optional()->numberBetween(20, 90),
            'sort_order' => 1,
        ];
    }
}
