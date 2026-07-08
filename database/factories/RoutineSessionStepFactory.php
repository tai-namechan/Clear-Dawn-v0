<?php

namespace Database\Factories;

use App\Enums\RoutineSessionStepStatus;
use App\Models\RoutineItem;
use App\Models\RoutineSession;
use App\Models\RoutineSessionStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutineSessionStep>
 */
class RoutineSessionStepFactory extends Factory
{
    protected $model = RoutineSessionStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'routine_session_id' => RoutineSession::factory(),
            'routine_item_id' => RoutineItem::factory(),
            'item_name' => fake()->words(2, true),
            'video_id' => null,
            'purpose' => null,
            'sort_order' => fake()->numberBetween(1, 10),
            'target_load' => fake()->optional()->randomFloat(2, 5, 100),
            'load_unit' => 'kg',
            'target_amount' => fake()->optional()->numberBetween(5, 15),
            'amount_unit' => 'reps',
            'target_blocks' => fake()->optional()->numberBetween(1, 5),
            'rest_seconds' => null,
            'status' => RoutineSessionStepStatus::Pending,
            'actual_duration_seconds' => null,
            'memo' => null,
        ];
    }
}
