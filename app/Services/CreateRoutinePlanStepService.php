<?php

namespace App\Services;

use App\Enums\RoutinePlanStatus;
use App\Enums\StepPurpose;
use App\Models\RoutinePlan;
use App\Models\RoutinePlanStep;
use Illuminate\Support\Facades\DB;

class CreateRoutinePlanStepService
{
    /**
     * sort_order はサーバー側で採番する。draft プランのみ追加可能（Policy で判定）。
     * 最初のステップ追加時に Draft プランは自動的に Ready になる。
     *
     * @param  array{
     *     routine_item_id: string,
     *     video_id?: string|null,
     *     purpose?: StepPurpose|string|null,
     *     target_load?: float|string|null,
     *     load_unit?: string|null,
     *     target_amount?: float|string|null,
     *     amount_unit?: string|null,
     *     target_blocks?: int|null,
     *     rest_seconds?: int|null,
     *     note?: string|null
     * }  $attributes
     */
    public function handle(RoutinePlan $plan, array $attributes): RoutinePlanStep
    {
        return DB::transaction(function () use ($plan, $attributes): RoutinePlanStep {
            $nextSortOrder = (int) $plan->steps()->max('sort_order') + 1;

            $step = $plan->steps()->create([
                'routine_item_id' => $attributes['routine_item_id'],
                'video_id' => $attributes['video_id'] ?? null,
                'purpose' => $attributes['purpose'] ?? null,
                'sort_order' => $nextSortOrder,
                'target_load' => $attributes['target_load'] ?? null,
                'load_unit' => $attributes['load_unit'] ?? null,
                'target_amount' => $attributes['target_amount'] ?? null,
                'amount_unit' => $attributes['amount_unit'] ?? null,
                'target_blocks' => $attributes['target_blocks'] ?? null,
                'rest_seconds' => $attributes['rest_seconds'] ?? null,
                'note' => $attributes['note'] ?? null,
            ]);

            if ($plan->status === RoutinePlanStatus::Draft && $plan->steps()->count() >= 1) {
                $plan->update(['status' => RoutinePlanStatus::Ready]);
            }

            return $step;
        });
    }
}
