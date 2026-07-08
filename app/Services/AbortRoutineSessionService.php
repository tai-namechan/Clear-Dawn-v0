<?php

namespace App\Services;

use App\Enums\RoutineSessionStatus;
use App\Models\RoutineSession;
use Illuminate\Support\Facades\DB;

class AbortRoutineSessionService
{
    /**
     * 実行を中断する（冪等: 既に aborted / completed なら変更しない）。
     */
    public function handle(RoutineSession $session): RoutineSession
    {
        return DB::transaction(function () use ($session): RoutineSession {
            if (in_array($session->status, [RoutineSessionStatus::Aborted, RoutineSessionStatus::Completed], true)) {
                return $session;
            }

            $session->update([
                'status' => RoutineSessionStatus::Aborted,
                'finished_at' => now(),
            ]);

            return $session->refresh();
        });
    }
}
