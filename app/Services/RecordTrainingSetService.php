<?php

namespace App\Services;

use App\Models\TrainingRunStep;
use App\Models\TrainingSetLog;
use Illuminate\Support\Facades\DB;

class RecordTrainingSetService
{
    /**
     * set_number はサーバー側で採番する。
     *
     * @param  array{
     *     weight_kg?: float|string|null,
     *     reps?: int|null,
     *     distance_m?: float|string|null,
     *     duration_seconds?: int|null,
     *     memo?: string|null
     * }  $attributes
     */
    public function handle(TrainingRunStep $runStep, array $attributes): TrainingSetLog
    {
        return DB::transaction(function () use ($runStep, $attributes): TrainingSetLog {
            $nextSetNumber = (int) $runStep->setLogs()->max('set_number') + 1;

            return $runStep->setLogs()->create([
                'set_number' => $nextSetNumber,
                'weight_kg' => $attributes['weight_kg'] ?? null,
                'reps' => $attributes['reps'] ?? null,
                'distance_m' => $attributes['distance_m'] ?? null,
                'duration_seconds' => $attributes['duration_seconds'] ?? null,
                'memo' => $attributes['memo'] ?? null,
            ]);
        });
    }
}
