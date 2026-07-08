<?php

namespace App\Queries;

use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetTrainingDayQuery
{
    /**
     * 指定日のトレーニングプランと実行状態を返す。
     *
     * @return Collection<int, TrainingPlan>
     */
    public function handle(User $user, Carbon $date): Collection
    {
        return TrainingPlan::query()
            ->where('user_id', $user->id)
            ->whereDate('scheduled_on', $date->toDateString())
            ->with([
                'lifeArea',
                'steps' => fn ($query) => $query->orderBy('sort_order'),
                'steps.exercise',
                'runs' => fn ($query) => $query->orderByDesc('started_at'),
            ])
            ->orderBy('created_at')
            ->get();
    }
}
