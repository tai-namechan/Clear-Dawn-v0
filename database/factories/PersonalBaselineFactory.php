<?php

namespace Database\Factories;

use App\Models\PersonalBaseline;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalBaseline>
 */
class PersonalBaselineFactory extends Factory
{
    protected $model = PersonalBaseline::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'resource_key' => fake()->randomElement(['sleep_quality', 'fatigue', 'mood']),
            'mean_value' => fake()->randomFloat(4, 3, 8),
            'stddev_value' => fake()->randomFloat(4, 0.5, 2),
            'sample_count' => fake()->numberBetween(0, 60),
            'window_start' => fake()->optional()->date(),
            'window_end' => fake()->optional()->date(),
            'computed_at' => fake()->optional()->dateTime(),
        ];
    }
}
