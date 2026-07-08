<?php

namespace App\Services;

use App\Enums\ActivityLogEventType;
use App\Enums\TrainingRunStatus;
use App\Models\ActivityLog;
use App\Models\TrainingRun;
use Illuminate\Support\Facades\DB;

class CompleteTrainingRunService
{
    /**
     * 実行を完了し、activity_logs に不変イベントを記録する（冪等）。
     *
     * 既に completed の場合は activity_log を重複作成しない。
     */
    public function handle(TrainingRun $run): TrainingRun
    {
        return DB::transaction(function () use ($run): TrainingRun {
            if ($run->status === TrainingRunStatus::Completed) {
                return $run;
            }

            $occurredAt = now();

            $run->update([
                'status' => TrainingRunStatus::Completed,
                'finished_at' => $occurredAt,
            ]);

            $alreadyLogged = ActivityLog::query()
                ->where('user_id', $run->user_id)
                ->where('event_type', ActivityLogEventType::TrainingRunCompleted)
                ->where('subject_type', $run->getMorphClass())
                ->where('subject_id', $run->id)
                ->exists();

            if (! $alreadyLogged) {
                ActivityLog::query()->create([
                    'user_id' => $run->user_id,
                    'event_type' => ActivityLogEventType::TrainingRunCompleted,
                    'subject_type' => $run->getMorphClass(),
                    'subject_id' => $run->id,
                    'occurred_at' => $occurredAt,
                ]);
            }

            return $run->refresh();
        });
    }
}
