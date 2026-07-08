<?php

namespace App\Services;

use App\Enums\ExerciseCategory;
use App\Enums\TrackingType;
use App\Models\Exercise;

class UpdateExerciseService
{
    /**
     * @param  array{
     *     name?: string,
     *     life_area_id?: string|null,
     *     category?: ExerciseCategory,
     *     tracking_type?: TrackingType,
     *     note?: string|null,
     *     is_active?: bool
     * }  $attributes
     */
    public function handle(Exercise $exercise, array $attributes): Exercise
    {
        $exercise->update($attributes);

        return $exercise->refresh();
    }
}
