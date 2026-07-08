<?php

namespace App\Services;

use App\Models\TrainingPlan;
use Illuminate\Support\Facades\DB;

class ReorderTrainingPlanStepsService
{
    /**
     * 渡された ID の並び順で sort_order を 1 から採番し直す。
     *
     * @param  list<string>  $orderedIds
     */
    public function handle(TrainingPlan $plan, array $orderedIds): void
    {
        DB::transaction(function () use ($plan, $orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                $plan->steps()
                    ->whereKey($id)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }
}
