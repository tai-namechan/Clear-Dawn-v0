<?php

namespace Database\Factories;

use App\Enums\ProgramStatus;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    protected $model = Program::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'goal_id' => null,
            'name' => fake()->words(3, true),
            'purpose' => fake()->optional()->sentence(),
            'design_philosophy' => null,
            'status' => ProgramStatus::Active,
        ];
    }
}
