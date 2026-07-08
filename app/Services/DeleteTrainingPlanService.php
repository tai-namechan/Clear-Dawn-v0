<?php

namespace App\Services;

use App\Models\TrainingPlan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeleteTrainingPlanService
{
    /**
     * 実行履歴が存在するプランは削除できない。
     */
    public function handle(TrainingPlan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            if ($plan->runs()->exists()) {
                throw new RuntimeException('Training plan with runs cannot be deleted.');
            }

            $plan->steps()->delete();
            $plan->delete();
        });
    }
}
