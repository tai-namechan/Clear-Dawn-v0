<?php

namespace App\Services;

use App\Models\RoutineStep;

class DeleteRoutineStepService
{
    public function handle(RoutineStep $step): void
    {
        $step->delete();
    }
}
