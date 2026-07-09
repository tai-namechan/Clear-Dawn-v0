<?php

namespace App\Services;

use App\Enums\RoutineItemCategory;
use App\Enums\TrackingType;
use App\Models\RoutineItem;

class UpdateRoutineItemService
{
    /**
     * @param  array{
     *     name?: string,
     *     life_area_id?: string|null,
     *     category?: RoutineItemCategory,
     *     tracking_type?: TrackingType,
     *     default_load_unit?: string|null,
     *     default_amount_unit?: string|null,
     *     default_video_id?: string|null,
     *     note?: string|null,
     *     is_active?: bool
     * }  $attributes
     */
    public function handle(RoutineItem $routineItem, array $attributes): RoutineItem
    {
        $routineItem->update($attributes);

        return $routineItem->refresh();
    }
}
