<?php

namespace App\Services;

use App\Enums\TrainingRunStatus;
use App\Enums\TrainingRunStepStatus;
use App\Enums\VideoStatus;
use App\Models\Exercise;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanStep;
use App\Models\TrainingRun;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StartTrainingRunService
{
    /**
     * プランをスナップショットして実行セッションを開始する。
     *
     * exercise_name と video_id は実行時点の値を解決する:
     * video_id = plan_step.video_id ?? exercise の最初の ready 動画。
     */
    public function handle(User $user, TrainingPlan $plan): TrainingRun
    {
        return DB::transaction(function () use ($user, $plan): TrainingRun {
            if ($plan->user_id !== $user->id) {
                throw new RuntimeException('Training plan does not belong to user.');
            }

            $plan->load(['steps' => fn ($query) => $query->orderBy('sort_order'), 'steps.exercise']);

            $run = $user->trainingRuns()->create([
                'training_plan_id' => $plan->id,
                'status' => TrainingRunStatus::InProgress,
                'started_at' => now(),
            ]);

            /** @var TrainingPlanStep $planStep */
            foreach ($plan->steps as $planStep) {
                $exercise = $planStep->exercise;
                $videoId = $planStep->video_id ?? $this->resolveFirstReadyVideoId($user, $exercise);

                $run->steps()->create([
                    'exercise_id' => $planStep->exercise_id,
                    'exercise_name' => $exercise->name,
                    'video_id' => $videoId,
                    'purpose' => $planStep->purpose,
                    'sort_order' => $planStep->sort_order,
                    'target_sets' => $planStep->target_sets,
                    'target_reps' => $planStep->target_reps,
                    'target_weight_kg' => $planStep->target_weight_kg,
                    'target_distance_m' => $planStep->target_distance_m,
                    'target_duration_seconds' => $planStep->target_duration_seconds,
                    'rest_seconds' => $planStep->rest_seconds,
                    'status' => TrainingRunStepStatus::Pending,
                ]);
            }

            return $run->load(['steps.setLogs', 'trainingPlan']);
        });
    }

    private function resolveFirstReadyVideoId(User $user, Exercise $exercise): ?string
    {
        /** @var Video|null $video */
        $video = Video::query()
            ->where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)
            ->where('status', VideoStatus::Ready)
            ->orderBy('created_at')
            ->first();

        return $video?->id;
    }
}
