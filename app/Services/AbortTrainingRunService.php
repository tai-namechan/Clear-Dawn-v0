<?php

namespace App\Services;

use App\Enums\TrainingRunStatus;
use App\Models\TrainingRun;
use Illuminate\Support\Facades\DB;

class AbortTrainingRunService
{
    /**
     * 実行を中断する（冪等: 既に aborted / completed なら変更しない）。
     */
    public function handle(TrainingRun $run): TrainingRun
    {
        return DB::transaction(function () use ($run): TrainingRun {
            if (in_array($run->status, [TrainingRunStatus::Aborted, TrainingRunStatus::Completed], true)) {
                return $run;
            }

            $run->update([
                'status' => TrainingRunStatus::Aborted,
                'finished_at' => now(),
            ]);

            return $run->refresh();
        });
    }
}
