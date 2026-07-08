<?php

namespace Database\Factories;

use App\Enums\TrainingRunStatus;
use App\Models\TrainingPlan;
use App\Models\TrainingRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingRun>
 */
class TrainingRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'training_plan_id' => TrainingPlan::factory(),
            'status' => TrainingRunStatus::InProgress,
            'started_at' => now(),
            'finished_at' => null,
            'note' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TrainingRunStatus::Completed,
            'finished_at' => now(),
        ]);
    }

    public function aborted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TrainingRunStatus::Aborted,
            'finished_at' => now(),
        ]);
    }
}
