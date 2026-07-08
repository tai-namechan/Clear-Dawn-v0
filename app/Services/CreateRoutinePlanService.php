<?php

namespace App\Services;

use App\Enums\RoutinePlanStatus;
use App\Models\Routine;
use App\Models\RoutinePlan;
use App\Models\RoutineStep;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateRoutinePlanService
{
    /**
     * 空のプラン、またはルーティンからステップをスナップショットして作成する。
     * ルーティンにステップが1件以上ある場合は Ready、空プランは Draft。
     *
     * @param  array{
     *     title: string,
     *     scheduled_on: \Illuminate\Support\Carbon|string,
     *     life_area_id?: string|null,
     *     routine_id?: string|null,
     *     note?: string|null
     * }  $attributes
     */
    public function handle(User $user, array $attributes, ?Routine $routine = null): RoutinePlan
    {
        return DB::transaction(function () use ($user, $attributes, $routine): RoutinePlan {
            $status = RoutinePlanStatus::Draft;
            $routineSteps = collect();

            if ($routine !== null) {
                $routine->load(['routineSteps' => fn ($query) => $query->orderBy('sort_order')]);
                $routineSteps = $routine->routineSteps;

                if ($routineSteps->count() >= 1) {
                    $status = RoutinePlanStatus::Ready;
                }
            }

            $plan = $user->routinePlans()->create([
                'title' => $attributes['title'],
                'scheduled_on' => $attributes['scheduled_on'],
                'life_area_id' => $attributes['life_area_id'] ?? ($routine !== null ? $routine->life_area_id : null),
                'routine_id' => $routine !== null ? $routine->id : ($attributes['routine_id'] ?? null),
                'note' => $attributes['note'] ?? null,
                'status' => $status,
            ]);

            /** @var RoutineStep $step */
            foreach ($routineSteps as $step) {
                $plan->steps()->create([
                    'routine_item_id' => $step->routine_item_id,
                    'video_id' => $step->video_id,
                    'purpose' => $step->purpose,
                    'sort_order' => $step->sort_order,
                    'target_load' => $step->target_load,
                    'load_unit' => $step->load_unit,
                    'target_amount' => $step->target_amount,
                    'amount_unit' => $step->amount_unit,
                    'target_blocks' => $step->target_blocks,
                    'rest_seconds' => $step->rest_seconds,
                    'note' => $step->note,
                ]);
            }

            return $plan->load('steps');
        });
    }
}
