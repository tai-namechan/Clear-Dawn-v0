<?php

namespace App\Services;

use App\Enums\GoalMetricDirection;
use App\Models\Goal;
use App\Models\GoalMetric;
use Illuminate\Support\Facades\DB;

class CreateGoalMetricService
{
    /**
     * @param  array{
     *     metric_id: string,
     *     baseline_value?: float|string|null,
     *     target_value?: float|string|null,
     *     target_low?: float|string|null,
     *     target_high?: float|string|null,
     *     direction?: GoalMetricDirection|null,
     *     note?: string|null,
     *     sort_order?: int
     * }  $attributes
     */
    public function handle(Goal $goal, array $attributes): GoalMetric
    {
        return DB::transaction(function () use ($goal, $attributes): GoalMetric {
            $goalMetric = $goal->goalMetrics()->create([
                'metric_id' => $attributes['metric_id'],
                'baseline_value' => $attributes['baseline_value'] ?? null,
                'target_value' => $attributes['target_value'] ?? null,
                'target_low' => $attributes['target_low'] ?? null,
                'target_high' => $attributes['target_high'] ?? null,
                'direction' => $attributes['direction'] ?? null,
                'note' => $attributes['note'] ?? null,
                'sort_order' => $attributes['sort_order'] ?? 0,
            ]);

            $goal->changeLogs()->create([
                'changes' => ['goal_metric_added' => ['metric_id' => $attributes['metric_id']]],
                'reason' => '達成指標を追加',
            ]);

            return $goalMetric;
        });
    }
}
