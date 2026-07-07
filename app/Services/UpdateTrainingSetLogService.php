<?php

namespace App\Services;

use App\Models\TrainingSetLog;

class UpdateTrainingSetLogService
{
    /**
     * @param  array{
     *     weight_kg?: float|string|null,
     *     reps?: int|null,
     *     distance_m?: float|string|null,
     *     duration_seconds?: int|null,
     *     memo?: string|null
     * }  $attributes
     */
    public function handle(TrainingSetLog $setLog, array $attributes): TrainingSetLog
    {
        $setLog->update($attributes);

        return $setLog->refresh();
    }
}
