<?php

namespace App\Queries;

use App\Models\DailyCheckin;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamCalendar;
use App\Support\TeamSafeBands;
use Illuminate\Support\Collection;

class TeamAthleteConditionQuery
{
    public function __construct(
        private TeamCalendar $calendar,
        private TeamSafeBands $bands,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Team $team, User $athlete, int $days = 14): array
    {
        [$start, $end] = $this->calendar->dayWindow($team, $days);
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        /** @var Collection<int, DailyCheckin> $checkins */
        $checkins = DailyCheckin::query()
            ->where('user_id', $athlete->id)
            ->whereDate('checked_on', '>=', $startDate)
            ->whereDate('checked_on', '<=', $endDate)
            ->orderByDesc('checked_on')
            ->get(['id', 'checked_on', 'readiness_self', 'fatigue']);

        return [
            'athlete' => [
                'id' => $athlete->id,
                'name' => $athlete->name,
                'status' => 'active',
            ],
            'period' => [
                'days' => $days,
                'timezone' => $this->calendar->timezone($team),
                'from' => $startDate,
                'to' => $endDate,
            ],
            'entries' => $checkins->map(fn (DailyCheckin $checkin): array => [
                'id' => $checkin->id,
                'date' => $checkin->checked_on->toDateString(),
                'recovery_level' => $this->bands->recoveryLabel(
                    $checkin->readiness_self,
                    $checkin->fatigue,
                ),
                'training_availability' => $this->bands->trainingAvailabilitySummary(
                    $checkin->readiness_self,
                    $checkin->fatigue,
                ),
            ])->values()->all(),
            'disclaimer' => 'この表示は記録の要約であり、健康状態の診断ではありません。',
        ];
    }
}
