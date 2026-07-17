<?php

namespace App\Queries;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class GetGoalsQuery
{
    /**
     * 階層ツリー表示用にトップレベル目標（子・指標つき）を返す。
     *
     * @return Collection<int, Goal>
     */
    public function handle(User $user): Collection
    {
        return Goal::query()
            ->where('user_id', $user->id)
            ->whereNull('parent_goal_id')
            ->with([
                'goalMetrics.metric',
                'children.goalMetrics.metric',
                'children.children.goalMetrics.metric',
            ])
            ->orderBy('sort_order')
            ->orderBy('priority')
            ->get();
    }
}
