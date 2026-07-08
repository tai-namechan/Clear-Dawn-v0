<?php

namespace App\Services;

use App\Models\RoutineBlockLog;

class UpdateRoutineBlockLogService
{
    /**
     * @param  array{
     *     load_value?: float|string|null,
     *     load_unit?: string|null,
     *     amount_value?: float|string|null,
     *     amount_unit?: string|null,
     *     memo?: string|null
     * }  $attributes
     */
    public function handle(RoutineBlockLog $blockLog, array $attributes): RoutineBlockLog
    {
        $blockLog->update($attributes);

        return $blockLog->refresh();
    }
}
