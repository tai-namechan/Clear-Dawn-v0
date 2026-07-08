<?php

namespace App\Services;

use App\Models\RoutineItem;

class DeleteRoutineItemService
{
    public function handle(RoutineItem $routineItem): void
    {
        $routineItem->delete();
    }
}
