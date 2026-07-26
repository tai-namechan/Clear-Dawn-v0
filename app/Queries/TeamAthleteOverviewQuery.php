<?php

namespace App\Queries;

use App\Models\DailyCheckin;
use App\Models\MealEntry;
use App\Models\RoutineSession;
use App\Models\SymptomObservation;
use App\Models\User;

class TeamAthleteOverviewQuery
{
    /** @return array<string, mixed> */
    public function handle(User $athlete): array
    {
        $lastTraining = RoutineSession::query()->where('user_id', $athlete->id)->latest('started_at')->first(['started_at']);
        $lastMeal = MealEntry::query()->where('user_id', $athlete->id)->latest('eaten_on')->first(['eaten_on']);
        $latestCheckin = DailyCheckin::query()->where('user_id', $athlete->id)->latest('checked_on')->first(['checked_on']);

        return [
            'id' => $athlete->id,
            'name' => $athlete->name,
            'status' => 'active',
            'training_sessions_7_days' => RoutineSession::query()->where('user_id', $athlete->id)->where('started_at', '>=', now()->subDays(7))->count(),
            'meal_record_days_7_days' => MealEntry::query()->where('user_id', $athlete->id)->where('eaten_on', '>=', today()->subDays(6))->distinct()->count('eaten_on'),
            'condition_recorded' => $latestCheckin !== null,
            'follow_up' => SymptomObservation::query()->where('user_id', $athlete->id)->where('observed_on', '>=', today()->subDays(7))->where('severity', '>=', 5)->exists(),
            'last_updated_at' => collect([$lastTraining?->started_at, $lastMeal?->eaten_on, $latestCheckin?->checked_on])->filter()->max()?->toIso8601String(),
            'weight_sharing_status' => 'sharing_not_configured',
        ];
    }
}
