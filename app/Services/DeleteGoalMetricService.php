<?php

namespace App\Services;

use App\Models\GoalMetric;
use Illuminate\Support\Facades\DB;

class DeleteGoalMetricService
{
    public function handle(GoalMetric $goalMetric): void
    {
        DB::transaction(function () use ($goalMetric): void {
            $goal = $goalMetric->goal;
            $metricId = $goalMetric->metric_id;

            $goalMetric->delete();

            $goal->changeLogs()->create([
                'changes' => ['goal_metric_removed' => ['metric_id' => $metricId]],
                'reason' => '達成指標を削除',
            ]);
        });
    }
}
