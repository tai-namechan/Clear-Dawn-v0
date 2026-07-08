<?php

namespace App\Services;

use App\Models\RoutinePlanStep;

class DeleteRoutinePlanStepService
{
    public function handle(RoutinePlanStep $step): void
    {
        $step->delete();
    }
}
