<?php

namespace App\Services;

use App\Models\GoalMetric;
use Illuminate\Support\Facades\DB;

/**
 * 達成指標の変更。変更内容と理由を goal_change_logs に記録する（goals.md）。
 */
class UpdateGoalMetricService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(GoalMetric $goalMetric, array $attributes, string $reason): GoalMetric
    {
        return DB::transaction(function () use ($goalMetric, $attributes, $reason): GoalMetric {
            $goalMetric->fill($attributes);

            $changes = [];

            foreach ($goalMetric->getDirty() as $field => $newValue) {
                $changes[$field] = [
                    'from' => $goalMetric->getOriginal($field) instanceof \BackedEnum
                        ? $goalMetric->getOriginal($field)->value
                        : $goalMetric->getOriginal($field),
                    'to' => $newValue instanceof \BackedEnum ? $newValue->value : $newValue,
                ];
            }

            if ($changes !== []) {
                $goalMetric->save();
                $goalMetric->goal->changeLogs()->create([
                    'changes' => ['goal_metric' => ['metric_id' => $goalMetric->metric_id, 'fields' => $changes]],
                    'reason' => $reason,
                ]);
            }

            return $goalMetric;
        });
    }
}
