<?php

namespace Database\Factories;

use App\Models\Metric;
use App\Models\MetricRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetricRecord>
 */
class MetricRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'metric_id' => Metric::query()->inRandomOrder()->value('id') ?? 'placeholder',
            'life_area_id' => null,
            'recorded_on' => fake()->date(),
            'value' => fake()->randomFloat(2, 1, 100),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
