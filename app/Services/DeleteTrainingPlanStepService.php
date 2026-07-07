<?php

namespace App\Services;

use App\Models\TrainingPlanStep;

class DeleteTrainingPlanStepService
{
    public function handle(TrainingPlanStep $step): void
    {
        $step->delete();
    }
}
