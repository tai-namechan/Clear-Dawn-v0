<?php

namespace Database\Factories;

use App\Enums\RoutineSessionStatus;
use App\Models\RoutinePlan;
use App\Models\RoutineSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutineSession>
 */
class RoutineSessionFactory extends Factory
{
    protected $model = RoutineSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'routine_plan_id' => RoutinePlan::factory(),
            'status' => RoutineSessionStatus::InProgress,
            'started_at' => now(),
            'finished_at' => null,
            'note' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RoutineSessionStatus::Completed,
            'finished_at' => now(),
        ]);
    }

    public function aborted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RoutineSessionStatus::Aborted,
            'finished_at' => now(),
        ]);
    }
}
