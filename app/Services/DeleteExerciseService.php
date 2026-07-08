<?php

namespace App\Services;

use App\Models\Exercise;

class DeleteExerciseService
{
    public function handle(Exercise $exercise): void
    {
        $exercise->delete();
    }
}
