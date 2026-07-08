<?php

namespace App\Services;

use App\Enums\RoutinePlanStatus;
use App\Models\RoutinePlan;
use Illuminate\Support\Carbon;

class UpdateRoutinePlanService
{
    /**
     * @param  array{
     *     title?: string,
     *     scheduled_on?: Carbon|string,
     *     life_area_id?: string|null,
     *     note?: string|null,
     *     status?: RoutinePlanStatus
     * }  $attributes
     */
    public function handle(RoutinePlan $plan, array $attributes): RoutinePlan
    {
        $plan->update($attributes);

        return $plan->refresh();
    }
}
