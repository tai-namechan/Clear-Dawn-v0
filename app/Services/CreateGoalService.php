<?php

namespace App\Services;

use App\Enums\GoalStatus;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateGoalService
{
    /**
     * @param  array{
     *     name: string,
     *     why?: string|null,
     *     parent_goal_id?: string|null,
     *     matrix_cell_id?: string|null,
     *     priority?: int,
     *     status?: GoalStatus,
     *     deadline?: string|null,
     *     sort_order?: int
     * }  $attributes
     */
    public function handle(User $user, array $attributes): Goal
    {
        return DB::transaction(function () use ($user, $attributes): Goal {
            return $user->goals()->create([
                'name' => $attributes['name'],
                'why' => $attributes['why'] ?? null,
                'parent_goal_id' => $attributes['parent_goal_id'] ?? null,
                'matrix_cell_id' => $attributes['matrix_cell_id'] ?? null,
                'priority' => $attributes['priority'] ?? 0,
                'status' => $attributes['status'] ?? GoalStatus::Active,
                'deadline' => $attributes['deadline'] ?? null,
                'sort_order' => $attributes['sort_order'] ?? 0,
            ]);
        });
    }
}
