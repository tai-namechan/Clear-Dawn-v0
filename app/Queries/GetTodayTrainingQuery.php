<?php

namespace App\Queries;

use App\Enums\TrainingPlanStatus;
use App\Models\User;
use Illuminate\Support\Carbon;

class GetTodayTrainingQuery
{
    public function __construct(
        private readonly GetTrainingDayQuery $getTrainingDayQuery,
    ) {}

    /**
     * ダッシュボード用: 本日のトレーニングプランと実行状態。
     *
     * @return array{
     *     date: string,
     *     plans: \Illuminate\Support\Collection<int, \App\Models\TrainingPlan>
     * }
     */
    public function handle(User $user, ?Carbon $date = null): array
    {
        $targetDate = ($date ?? now())->startOfDay();

        return [
            'date' => $targetDate->toDateString(),
            'plans' => $this->getTrainingDayQuery->handle($user, $targetDate)
                ->reject(fn ($plan) => $plan->status === TrainingPlanStatus::Archived)
                ->values(),
        ];
    }
}
