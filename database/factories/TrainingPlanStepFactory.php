<?php

namespace Database\Factories;

use App\Enums\StepPurpose;
use App\Models\Exercise;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingPlanStep>
 */
class TrainingPlanStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_plan_id' => TrainingPlan::factory(),
            'exercise_id' => Exercise::factory(),
            'video_id' => null,
            'purpose' => fake()->optional()->randomElement(StepPurpose::cases()),
            'sort_order' => fake()->numberBetween(1, 10),
            'target_sets' => fake()->optional()->numberBetween(1, 5),
            'target_reps' => fake()->optional()->numberBetween(5, 15),
            'target_weight_kg' => fake()->optional()->randomFloat(2, 5, 100),
            'target_distance_m' => null,
            'target_duration_seconds' => null,
            'rest_seconds' => fake()->optional()->numberBetween(30, 120),
            'note' => fake()->optional()->sentence(),
        ];
    }

    public function forPlan(TrainingPlan $plan): static
    {
        return $this->state(fn (array $attributes) => [
            'training_plan_id' => $plan->id,
            'exercise_id' => Exercise::factory()->create(['user_id' => $plan->user_id])->id,
        ]);
    }
}
