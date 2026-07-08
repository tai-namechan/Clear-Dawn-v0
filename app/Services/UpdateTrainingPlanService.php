<?php

namespace App\Services;

use App\Enums\TrainingPlanStatus;
use App\Models\TrainingPlan;
use Illuminate\Support\Carbon;

class UpdateTrainingPlanService
{
    /**
     * @param  array{
     *     title?: string,
     *     scheduled_on?: Carbon|string,
     *     life_area_id?: string|null,
     *     note?: string|null,
     *     status?: TrainingPlanStatus
     * }  $attributes
     */
    public function handle(TrainingPlan $plan, array $attributes): TrainingPlan
    {
        $plan->update($attributes);

        return $plan->refresh();
    }
}
