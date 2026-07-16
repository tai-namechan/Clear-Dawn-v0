<?php

namespace Database\Factories;

use App\Enums\GoalStatus;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    protected $model = Goal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'parent_goal_id' => null,
            'matrix_cell_id' => null,
            'name' => fake()->words(3, true),
            'why' => fake()->optional()->sentence(),
            'priority' => fake()->numberBetween(0, 5),
            'status' => GoalStatus::Active,
            'deadline' => fake()->optional()->date(),
            'sort_order' => 0,
        ];
    }
}
