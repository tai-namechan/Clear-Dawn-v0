<?php

namespace App\Queries;

use App\Models\Goal;
use App\Models\User;

class GetGoalQuery
{
    public function handle(User $user, string $goalId): Goal
    {
        return Goal::query()
            ->where('user_id', $user->id)
            ->with([
                'parent',
                'children.goalMetrics.metric',
                'goalMetrics.metric',
                'matrixCell.lifeArea',
                'programs.versions',
                'changeLogs',
            ])
            ->findOrFail($goalId);
    }
}
