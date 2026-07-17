<?php

namespace Database\Factories;

use App\Enums\GoalMetricDirection;
use App\Models\Goal;
use App\Models\GoalMetric;
use App\Models\Metric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoalMetric>
 */
class GoalMetricFactory extends Factory
{
    protected $model = GoalMetric::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goal_id' => Goal::factory(),
            'metric_id' => fn () => Metric::query()->firstOrCreate(
                ['key' => 'test_metric_'.fake()->unique()->numberBetween(1, 100000)],
                ['label' => 'テスト指標', 'unit' => 'kg', 'value_type' => 'decimal', 'sort_order' => 999],
            )->id,
            'baseline_value' => fake()->randomFloat(1, 40, 100),
            'target_value' => fake()->randomFloat(1, 60, 200),
            'direction' => GoalMetricDirection::Increase,
            'sort_order' => 0,
        ];
    }
}
