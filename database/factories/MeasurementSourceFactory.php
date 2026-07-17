<?php

namespace Database\Factories;

use App\Models\MeasurementSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeasurementSource>
 */
class MeasurementSourceFactory extends Factory
{
    protected $model = MeasurementSource::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'key' => fake()->unique()->slug(2),
            'label' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
