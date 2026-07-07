<?php

namespace App\Services;

use App\Models\Routine;
use Illuminate\Support\Facades\DB;

class ReorderRoutineStepsService
{
    /**
     * 渡された ID の並び順で sort_order を 1 から採番し直す。
     *
     * @param  list<string>  $orderedIds
     */
    public function handle(Routine $routine, array $orderedIds): void
    {
        DB::transaction(function () use ($routine, $orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                $routine->routineSteps()
                    ->whereKey($id)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }
}
