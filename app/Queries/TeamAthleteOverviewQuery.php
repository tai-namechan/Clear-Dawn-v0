<?php

namespace App\Queries;

use App\Enums\GoalStatus;
use App\Models\DailyCheckin;
use App\Models\Goal;
use App\Models\MealEntry;
use App\Models\RoutineSession;
use App\Models\SymptomObservation;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamCalendar;
use App\Support\TeamSafeBands;

class TeamAthleteOverviewQuery
{
    public function __construct(
        private TeamCalendar $calendar,
        private TeamSafeBands $bands,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Team $team, User $athlete): array
    {
        [$start, $end] = $this->calendar->dayWindow($team, 7);
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $lastTraining = RoutineSession::query()
            ->where('user_id', $athlete->id)
            ->latest('started_at')
            ->first(['started_at']);
        $lastMeal = MealEntry::query()
            ->where('user_id', $athlete->id)
            ->latest('eaten_on')
            ->first(['eaten_on']);
        $latestCheckin = DailyCheckin::query()
            ->where('user_id', $athlete->id)
            ->latest('checked_on')
            ->first(['checked_on', 'readiness_self', 'fatigue']);

        $goals = Goal::query()
            ->where('user_id', $athlete->id)
            ->whereIn('status', [GoalStatus::Active, GoalStatus::Achieved])
            ->get(['status']);

        $needsFollowUp = SymptomObservation::query()
            ->where('user_id', $athlete->id)
            ->whereDate('observed_on', '>=', $startDate)
            ->whereDate('observed_on', '<=', $endDate)
            ->where('severity', '>=', 5)
            ->exists();

        return [
            'id' => $athlete->id,
            'name' => $athlete->name,
            'status' => 'active',
            'training_sessions_7_days' => RoutineSession::query()
                ->where('user_id', $athlete->id)
                ->where('started_at', '>=', $start)
                ->where('started_at', '<=', $end)
                ->count(),
            'meal_record_days_7_days' => MealEntry::query()
                ->where('user_id', $athlete->id)
                ->whereDate('eaten_on', '>=', $startDate)
                ->whereDate('eaten_on', '<=', $endDate)
                ->distinct()
                ->count('eaten_on'),
            'condition_summary' => $this->bands->recoveryLabel(
                $latestCheckin?->readiness_self,
                $latestCheckin?->fatigue,
            ),
            'condition_recorded' => $latestCheckin !== null,
            'follow_up' => $needsFollowUp,
            'goals_active_count' => $goals->where('status', GoalStatus::Active)->count(),
            'goals_achieved_count' => $goals->where('status', GoalStatus::Achieved)->count(),
            'last_updated_at' => collect([
                $lastTraining?->started_at,
                $lastMeal?->eaten_on,
                $latestCheckin?->checked_on,
            ])->filter()->max()?->toIso8601String(),
            'weight_sharing_status' => 'sharing_not_configured',
            'period' => [
                'days' => 7,
                'timezone' => $this->calendar->timezone($team),
                'from' => $startDate,
                'to' => $endDate,
            ],
        ];
    }
}
