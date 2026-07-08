<?php

namespace App\Services;

use App\Enums\StepPurpose;
use App\Models\RoutineStep;

class UpdateRoutineStepService
{
    /**
     * @param  array{
     *     routine_item_id?: string,
     *     video_id?: string|null,
     *     purpose?: StepPurpose|null,
     *     target_load?: float|string|null,
     *     load_unit?: string|null,
     *     target_amount?: float|string|null,
     *     amount_unit?: string|null,
     *     target_blocks?: int|null,
     *     rest_seconds?: int|null,
     *     note?: string|null
     * }  $attributes
     */
    public function handle(RoutineStep $step, array $attributes): RoutineStep
    {
        $step->update($attributes);

        return $step->refresh();
    }
}
