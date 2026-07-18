<?php

namespace Database\Factories;

use App\Models\DailyCheckin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyCheckin>
 */
class DailyCheckinFactory extends Factory
{
    protected $model = DailyCheckin::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'checked_on' => fake()->unique()->date(),
            'sleep_quality' => fake()->numberBetween(0, 10),
            'fatigue' => fake()->numberBetween(0, 10),
            'muscle_soreness' => fake()->numberBetween(0, 10),
            'stress' => fake()->numberBetween(0, 10),
            'mood' => fake()->numberBetween(0, 10),
            'region_tension' => null,
            'readiness_self' => fake()->numberBetween(0, 10),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
