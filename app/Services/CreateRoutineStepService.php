<?php

namespace App\Services;

use App\Enums\StepPurpose;
use App\Models\Routine;
use App\Models\RoutineStep;
use Illuminate\Support\Facades\DB;

class CreateRoutineStepService
{
    /**
     * sort_order はサーバー側で採番する。
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
    public function handle(Routine $routine, array $attributes): RoutineStep
    {
        return DB::transaction(function () use ($routine, $attributes): RoutineStep {
            $nextSortOrder = (int) $routine->routineSteps()->withTrashed()->max('sort_order') + 1;

            return $routine->routineSteps()->create([
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
        });
    }
}
