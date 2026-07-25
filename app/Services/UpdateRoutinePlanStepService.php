<?php

namespace App\Services;

use App\Enums\RoutineSessionStatus;
use App\Enums\RoutineSessionStepStatus;
use App\Enums\StepPurpose;
use App\Models\RoutinePlanStep;
use App\Models\RoutineSession;
use App\Support\RoutineStepDisplay;
use Illuminate\Support\Facades\DB;

class UpdateRoutinePlanStepService
{
    /**
     * @param  array{
     *     routine_item_id?: string,
     *     title?: string|null,
     *     video_id?: string|null,
     *     purpose?: StepPurpose|null,
     *     target_load?: float|string|null,
     *     load_unit?: string|null,
     *     target_amount?: float|string|null,
     *     amount_unit?: string|null,
     *     target_blocks?: int|null,
     *     rest_seconds?: int|null,
     *     note?: string|null
     * }  $attributes
     */
    public function handle(RoutinePlanStep $step, array $attributes): RoutinePlanStep
    {
        return DB::transaction(function () use ($step, $attributes): RoutinePlanStep {
            if (array_key_exists('title', $attributes)) {
                $title = $attributes['title'];
                if ($title !== null) {
                    $trimmed = trim((string) $title);
                    $attributes['title'] = $trimmed === '' ? null : $trimmed;
                }
            }

            $syncVideo = array_key_exists('video_id', $attributes);

            $step->update($attributes);
            $step = $step->refresh()->loadMissing('routineItem');

            // 実行中セッションの未完了ステップへ動画だけ同期する。
            // 目標値などはスナップショット不変のまま（動画未紐付けの当日修正を実行画面へ反映するため）。
            if ($syncVideo) {
                $this->syncVideoToInProgressPendingStep($step);
            }

            return $step;
        });
    }

    private function syncVideoToInProgressPendingStep(RoutinePlanStep $step): void
    {
        /** @var RoutineSession|null $session */
        $session = RoutineSession::query()
            ->where('routine_plan_id', $step->routine_plan_id)
            ->where('status', RoutineSessionStatus::InProgress)
            ->first();

        if ($session === null) {
            return;
        }

        $resolvedVideoId = RoutineStepDisplay::resolveVideoId(
            $step->video_id,
            $step->routineItem,
        );

        $session->steps()
            ->where('status', RoutineSessionStepStatus::Pending)
            ->where('sort_order', $step->sort_order)
            ->update(['video_id' => $resolvedVideoId]);
    }
}
