<?php

namespace App\Services;

use App\Models\Routine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateRoutineService
{
    /**
     * sort_order はサーバー側で採番する。
     *
     * @param  array{
     *     name: string,
     *     life_area_id?: string|null,
     *     description?: string|null,
     *     is_active?: bool
     * }  $attributes
     */
    public function handle(User $user, array $attributes): Routine
    {
        return DB::transaction(function () use ($user, $attributes): Routine {
            $nextSortOrder = (int) $user->routines()->withTrashed()->max('sort_order') + 1;

            return $user->routines()->create([
                'name' => $attributes['name'],
                'life_area_id' => $attributes['life_area_id'] ?? null,
                'description' => $attributes['description'] ?? null,
                'is_active' => $attributes['is_active'] ?? true,
                'sort_order' => $nextSortOrder,
            ]);
        });
    }
}
