<?php

namespace App\Services;

use App\Models\RoutineBlockLog;

class DeleteRoutineBlockLogService
{
    public function handle(RoutineBlockLog $blockLog): void
    {
        $blockLog->delete();
    }
}
