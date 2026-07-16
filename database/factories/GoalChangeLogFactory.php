<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\GoalChangeLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoalChangeLog>
 */
class GoalChangeLogFactory extends Factory
{
    protected $model = GoalChangeLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goal_id' => Goal::factory(),
            'changes' => ['deadline' => ['old' => '2026-09-01', 'new' => '2026-10-01']],
            'reason' => fake()->sentence(),
        ];
    }
}
