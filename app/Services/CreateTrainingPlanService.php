<?php

namespace App\Services;

use App\Enums\TrainingPlanStatus;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateTrainingPlanService
{
    /**
     * 空のプラン、またはルーティンからステップをスナップショットして作成する。
     *
     * @param  array{
     *     title: string,
     *     scheduled_on: Carbon|string,
     *     life_area_id?: string|null,
     *     routine_id?: string|null,
     *     note?: string|null
     * }  $attributes
     */
    public function handle(User $user, array $attributes, ?Routine $routine = null): TrainingPlan
    {
        return DB::transaction(function () use ($user, $attributes, $routine): TrainingPlan {
            $plan = $user->trainingPlans()->create([
                'title' => $attributes['title'],
                'scheduled_on' => $attributes['scheduled_on'],
                'life_area_id' => $attributes['life_area_id'] ?? $routine?->life_area_id,
                'routine_id' => $routine?->id ?? ($attributes['routine_id'] ?? null),
                'note' => $attributes['note'] ?? null,
                'status' => TrainingPlanStatus::Draft,
            ]);

            if ($routine !== null) {
                $routine->load(['routineSteps' => fn ($query) => $query->orderBy('sort_order')]);

                /** @var RoutineStep $step */
                foreach ($routine->routineSteps as $step) {
                    $plan->steps()->create([
                        'exercise_id' => $step->exercise_id,
                        'video_id' => $step->video_id,
                        'purpose' => $step->purpose,
                        'sort_order' => $step->sort_order,
                        'target_sets' => $step->target_sets,
                        'target_reps' => $step->target_reps,
                        'target_weight_kg' => $step->target_weight_kg,
                        'target_distance_m' => $step->target_distance_m,
                        'target_duration_seconds' => $step->target_duration_seconds,
                        'rest_seconds' => $step->rest_seconds,
                        'note' => $step->note,
                    ]);
                }
            }

            return $plan->load('steps');
        });
    }
}
