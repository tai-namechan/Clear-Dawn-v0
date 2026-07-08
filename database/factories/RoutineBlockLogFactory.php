<?php

namespace Database\Factories;

use App\Models\RoutineBlockLog;
use App\Models\RoutineSessionStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutineBlockLog>
 */
class RoutineBlockLogFactory extends Factory
{
    protected $model = RoutineBlockLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'routine_session_step_id' => RoutineSessionStep::factory(),
            'block_number' => 1,
            'load_value' => fake()->optional()->randomFloat(2, 5, 100),
            'load_unit' => 'kg',
            'amount_value' => fake()->optional()->numberBetween(5, 15),
            'amount_unit' => 'reps',
            'memo' => null,
        ];
    }
}
