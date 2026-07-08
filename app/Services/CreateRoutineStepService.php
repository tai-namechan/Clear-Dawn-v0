<?php

namespace App\Services;

use App\Enums\StepPurpose;
use App\Models\Routine;
use App\Models\RoutineStep;
use Illuminate\Support\Facades\DB;

class CreateRoutineStepService
{
    /**
     * sort_order はサーバー側で採番する。
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
    public function handle(Routine $routine, array $attributes): RoutineStep
    {
        return DB::transaction(function () use ($routine, $attributes): RoutineStep {
            $nextSortOrder = (int) $routine->routineSteps()->withTrashed()->max('sort_order') + 1;

            return $routine->routineSteps()->create([
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
