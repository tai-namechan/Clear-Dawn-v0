<?php

namespace App\Services;

use App\Models\RoutinePlan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeleteRoutinePlanService
{
    /**
     * 実行履歴が存在するプランは削除できない。
     */
    public function handle(RoutinePlan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            if ($plan->sessions()->exists()) {
                throw new RuntimeException('Routine plan with sessions cannot be deleted.');
            }

            $plan->steps()->delete();
            $plan->delete();
        });
    }
}
