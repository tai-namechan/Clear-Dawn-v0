<?php

namespace Database\Factories;

use App\Enums\TrainingPlanStatus;
use App\Models\Routine;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingPlan>
 */
class TrainingPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'life_area_id' => null,
            'routine_id' => null,
            'title' => fake()->words(3, true),
            'scheduled_on' => fake()->date(),
            'status' => TrainingPlanStatus::Draft,
            'note' => fake()->optional()->sentence(),
        ];
    }

    public function forRoutine(Routine $routine): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $routine->user_id,
            'life_area_id' => $routine->life_area_id,
            'routine_id' => $routine->id,
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TrainingPlanStatus::Ready,
        ]);
    }
}
