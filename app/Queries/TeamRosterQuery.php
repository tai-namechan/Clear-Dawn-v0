<?php

namespace App\Queries;

use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TeamRosterQuery
{
    public function handle(Team $team, ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $athleteIds = $team->memberships()->where('member_type', 'user')->where('role', 'athlete')->where('status', 'active')->select('member_id');

        return User::query()
            ->select(['users.id', 'users.name'])
            ->whereIn('users.id', $athleteIds)
            ->when($search, fn ($query, $value) => $query->where('name', 'like', '%'.$value.'%'))
            ->withCount([
                'routineSessions as routine_sessions_last_7_days_count' => fn ($query) => $query->where('started_at', '>=', now()->subDays(7)),
                'mealEntries as meal_days_last_7_days_count' => fn ($query) => $query->where('eaten_on', '>=', today()->subDays(6))->selectRaw('count(distinct eaten_on)'),
            ])
            ->withMax('mealEntries as last_meal_recorded_on', 'eaten_on')
            ->withMax('routineSessions as last_training_recorded_at', 'started_at')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }
}
