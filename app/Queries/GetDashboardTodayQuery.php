<?php

namespace App\Queries;

use App\Enums\RoutinePlanStatus;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetDashboardTodayQuery
{
    public function __construct(
        private readonly GetTodayQuery $getTodayQuery,
    ) {}

    /**
     * ダッシュボード用: 本日のルーティンプランと実行状態。
     *
     * @return array{
     *     date: string,
     *     plans: Collection<int, \App\Models\RoutinePlan>
     * }
     */
    public function handle(User $user, ?Carbon $date = null): array
    {
        $targetDate = Carbon::parse($date ?? now())->startOfDay();

        return [
            'date' => $targetDate->toDateString(),
            'plans' => $this->getTodayQuery->handle($user, $targetDate)
                ->reject(fn ($plan) => $plan->status === RoutinePlanStatus::Archived)
                ->values(),
        ];
    }
}
