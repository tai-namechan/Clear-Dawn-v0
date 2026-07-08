<?php

namespace App\Services;

use App\Enums\ActivityLogEventType;
use App\Enums\RoutineSessionStatus;
use App\Models\ActivityLog;
use App\Models\RoutineSession;
use Illuminate\Support\Facades\DB;

class CompleteRoutineSessionService
{
    /**
     * 実行を完了し、activity_logs に不変イベントを記録する（冪等）。
     *
     * 既に completed の場合は activity_log を重複作成しない。
     */
    public function handle(RoutineSession $session): RoutineSession
    {
        return DB::transaction(function () use ($session): RoutineSession {
            if ($session->status === RoutineSessionStatus::Completed) {
                return $session;
            }

            $occurredAt = now();

            $session->update([
                'status' => RoutineSessionStatus::Completed,
                'finished_at' => $occurredAt,
            ]);

            $alreadyLogged = ActivityLog::query()
                ->where('user_id', $session->user_id)
                ->where('event_type', ActivityLogEventType::RoutineSessionCompleted)
                ->where('subject_type', $session->getMorphClass())
                ->where('subject_id', $session->id)
                ->exists();

            if (! $alreadyLogged) {
                ActivityLog::query()->create([
                    'user_id' => $session->user_id,
                    'event_type' => ActivityLogEventType::RoutineSessionCompleted,
                    'subject_type' => $session->getMorphClass(),
                    'subject_id' => $session->id,
                    'occurred_at' => $occurredAt,
                ]);
            }

            return $session->refresh();
        });
    }
}
