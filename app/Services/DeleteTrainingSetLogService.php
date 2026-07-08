<?php

namespace App\Services;

use App\Models\TrainingSetLog;

class DeleteTrainingSetLogService
{
    public function handle(TrainingSetLog $setLog): void
    {
        $setLog->delete();
    }
}
