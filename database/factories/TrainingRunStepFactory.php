<?php

namespace Database\Factories;

use App\Enums\TrainingRunStepStatus;
use App\Models\Exercise;
use App\Models\TrainingRun;
use App\Models\TrainingRunStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingRunStep>
 */
class TrainingRunStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_run_id' => TrainingRun::factory(),
            'exercise_id' => Exercise::factory(),
            'exercise_name' => fake()->words(2, true),
            'video_id' => null,
            'purpose' => null,
            'sort_order' => fake()->numberBetween(1, 10),
            'target_sets' => fake()->optional()->numberBetween(1, 5),
            'target_reps' => fake()->optional()->numberBetween(5, 15),
            'target_weight_kg' => fake()->optional()->randomFloat(2, 5, 100),
            'target_distance_m' => null,
            'target_duration_seconds' => null,
            'rest_seconds' => null,
            'status' => TrainingRunStepStatus::Pending,
            'actual_duration_seconds' => null,
            'memo' => null,
        ];
    }
}
