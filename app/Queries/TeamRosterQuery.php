<?php

namespace App\Queries;

use App\Models\MealEntry;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamCalendar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TeamRosterQuery
{
    public function __construct(private TeamCalendar $calendar) {}

    public function handle(Team $team, ?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        [$start, $end] = $this->calendar->dayWindow($team, 7);
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $athleteIds = $team->memberships()
            ->where('member_type', 'user')
            ->where('role', 'athlete')
            ->where('status', 'active')
            ->select('member_id');

        return User::query()
            ->select(['users.id', 'users.name'])
            ->whereIn('users.id', $athleteIds)
            ->when($search, fn ($query, $value) => $query->where('name', 'like', '%'.$value.'%'))
            ->withCount([
                'routineSessions as routine_sessions_last_7_days_count' => fn ($query) => $query
                    ->where('started_at', '>=', $start)
                    ->where('started_at', '<=', $end),
            ])
            ->addSelect([
                'meal_days_last_7_days_count' => MealEntry::query()
                    ->selectRaw('count(distinct eaten_on)')
                    ->whereColumn('meal_entries.user_id', 'users.id')
                    ->whereDate('eaten_on', '>=', $startDate)
                    ->whereDate('eaten_on', '<=', $endDate),
            ])
            ->withMax('mealEntries as last_meal_recorded_on', 'eaten_on')
            ->withMax('routineSessions as last_training_recorded_at', 'started_at')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }
}
