<?php

namespace App\Services;

use App\Models\Routine;

class DeleteRoutineService
{
    public function handle(Routine $routine): void
    {
        $routine->delete();
    }
}
