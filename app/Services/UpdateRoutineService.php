<?php

namespace App\Services;

use App\Models\Routine;

class UpdateRoutineService
{
    /**
     * @param  array{
     *     name?: string,
     *     life_area_id?: string|null,
     *     description?: string|null,
     *     is_active?: bool
     * }  $attributes
     */
    public function handle(Routine $routine, array $attributes): Routine
    {
        $routine->update($attributes);

        return $routine->refresh();
    }
}
