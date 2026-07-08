<?php

namespace App\Queries;

use App\Models\RoutinePlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetTodayQuery
{
    /**
     * 指定日のルーティンプランと実行状態を返す。
     *
     * @return Collection<int, RoutinePlan>
     */
    public function handle(User $user, Carbon $date): Collection
    {
        return RoutinePlan::query()
            ->where('user_id', $user->id)
            ->whereDate('scheduled_on', $date->toDateString())
            ->with([
                'lifeArea',
                'steps' => fn ($query) => $query->orderBy('sort_order'),
                'steps.routineItem',
                'sessions' => fn ($query) => $query->orderByDesc('started_at'),
            ])
            ->orderBy('created_at')
            ->get();
    }
}
