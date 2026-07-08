<?php

namespace App\Services;

use App\Enums\StepPurpose;
use App\Models\TrainingPlanStep;

class UpdateTrainingPlanStepService
{
    /**
     * @param  array{
     *     exercise_id?: string,
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
    public function handle(TrainingPlanStep $step, array $attributes): TrainingPlanStep
    {
        $step->update($attributes);

        return $step->refresh();
    }
}
