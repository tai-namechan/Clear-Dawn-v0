<?php

namespace App\Services;

use App\Enums\StepPurpose;
use App\Models\RoutinePlanStep;

class UpdateRoutinePlanStepService
{
    /**
     * @param  array{
     *     routine_item_id?: string,
     *     title?: string|null,
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
    public function handle(RoutinePlanStep $step, array $attributes): RoutinePlanStep
    {
        if (array_key_exists('title', $attributes)) {
            $title = $attributes['title'];
            if ($title !== null) {
                $trimmed = trim((string) $title);
                $attributes['title'] = $trimmed === '' ? null : $trimmed;
            }
        }

        $step->update($attributes);

        return $step->refresh();
    }
}
