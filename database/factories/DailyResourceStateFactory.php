<?php

namespace Database\Factories;

use App\Models\DailyResourceState;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyResourceState>
 */
class DailyResourceStateFactory extends Factory
{
    protected $model = DailyResourceState::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'state_on' => fake()->date(),
            'resource_key' => fake()->randomElement(['sleep_quality', 'fatigue', 'mood']),
            'ewma' => fake()->randomFloat(4, 0, 10),
            'z_load' => fake()->optional()->randomFloat(4, -3, 3),
            'rel_strain' => fake()->optional()->randomFloat(4, 0, 3),
            'readiness' => fake()->optional()->randomFloat(4, 0, 10),
            'inputs_snapshot' => null,
        ];
    }
}
