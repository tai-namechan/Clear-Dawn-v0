<?php

namespace App\Services;

use App\Enums\StepPurpose;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanStep;
use Illuminate\Support\Facades\DB;

class CreateTrainingPlanStepService
{
    /**
     * sort_order はサーバー側で採番する。draft プランのみ追加可能（Policy で判定）。
     *
     * @param  array{
     *     exercise_id: string,
     *     video_id?: string|null,
     *     purpose?: StepPurpose|null,
     *     target_sets?: int|null,
     *     target_reps?: int|null,
     *     target_weight_kg?: float|string|null,
     *     target_distance_m?: float|string|null,
     *     target_duration_seconds?: int|null,
     *     rest_seconds?: int|null,
     *     note?: string|null
     * }  $attributes
     */
    public function handle(TrainingPlan $plan, array $attributes): TrainingPlanStep
    {
        return DB::transaction(function () use ($plan, $attributes): TrainingPlanStep {
            $nextSortOrder = (int) $plan->steps()->max('sort_order') + 1;

            return $plan->steps()->create([
                'exercise_id' => $attributes['exercise_id'],
                'video_id' => $attributes['video_id'] ?? null,
                'purpose' => $attributes['purpose'] ?? null,
                'sort_order' => $nextSortOrder,
                'target_sets' => $attributes['target_sets'] ?? null,
                'target_reps' => $attributes['target_reps'] ?? null,
                'target_weight_kg' => $attributes['target_weight_kg'] ?? null,
                'target_distance_m' => $attributes['target_distance_m'] ?? null,
                'target_duration_seconds' => $attributes['target_duration_seconds'] ?? null,
                'rest_seconds' => $attributes['rest_seconds'] ?? null,
                'note' => $attributes['note'] ?? null,
            ]);
        });
    }
}
