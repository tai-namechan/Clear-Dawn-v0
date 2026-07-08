<?php

namespace App\Services;

use App\Enums\RoutineSessionStatus;
use App\Enums\RoutineSessionStepStatus;
use App\Enums\VideoStatus;
use App\Models\RoutineItem;
use App\Models\RoutinePlan;
use App\Models\RoutinePlanStep;
use App\Models\RoutineSession;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StartRoutineSessionService
{
    /**
     * プランをスナップショットして実行セッションを開始する。
     *
     * routine_item_id と item_name は実行時点の値を解決する:
     * video_id = plan_step.video_id ?? routine item の最初の ready 動画。
     */
    public function handle(User $user, RoutinePlan $plan): RoutineSession
    {
        return DB::transaction(function () use ($user, $plan): RoutineSession {
            if ($plan->user_id !== $user->id) {
                throw new RuntimeException('Routine plan does not belong to user.');
            }

            $plan->load(['steps' => fn ($query) => $query->orderBy('sort_order'), 'steps.routineItem']);

            $session = $user->routineSessions()->create([
                'routine_plan_id' => $plan->id,
                'status' => RoutineSessionStatus::InProgress,
                'started_at' => now(),
            ]);

            /** @var RoutinePlanStep $planStep */
            foreach ($plan->steps as $planStep) {
                $routineItem = $planStep->routineItem;
                $videoId = $planStep->video_id ?? $this->resolveFirstReadyVideoId($user, $routineItem);

                $session->steps()->create([
                    'routine_item_id' => $planStep->routine_item_id,
                    'item_name' => $routineItem->name,
                    'video_id' => $videoId,
                    'purpose' => $planStep->purpose,
                    'sort_order' => $planStep->sort_order,
                    'target_load' => $planStep->target_load,
                    'load_unit' => $planStep->load_unit,
                    'target_amount' => $planStep->target_amount,
                    'amount_unit' => $planStep->amount_unit,
                    'target_blocks' => $planStep->target_blocks,
                    'rest_seconds' => $planStep->rest_seconds,
                    'status' => RoutineSessionStepStatus::Pending,
                ]);
            }

            return $session->load(['steps.blockLogs', 'routinePlan']);
        });
    }

    private function resolveFirstReadyVideoId(User $user, RoutineItem $routineItem): ?string
    {
        /** @var Video|null $video */
        $video = Video::query()
            ->where('user_id', $user->id)
            ->where('routine_item_id', $routineItem->id)
            ->where('status', VideoStatus::Ready)
            ->orderBy('created_at')
            ->first();

        return $video?->id;
    }
}
