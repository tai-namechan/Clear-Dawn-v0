<?php

namespace Database\Factories;

use App\Models\TrainingRunStep;
use App\Models\TrainingSetLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingSetLog>
 */
class TrainingSetLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_run_step_id' => TrainingRunStep::factory(),
            'set_number' => 1,
            'weight_kg' => fake()->optional()->randomFloat(2, 5, 100),
            'reps' => fake()->optional()->numberBetween(5, 15),
            'distance_m' => null,
            'duration_seconds' => null,
            'memo' => null,
        ];
    }
}
