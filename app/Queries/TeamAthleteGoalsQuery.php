<?php

namespace App\Queries;

use App\Models\Goal;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamCalendar;
use App\Support\TeamSafeBands;
use Illuminate\Support\Collection;

class TeamAthleteGoalsQuery
{
    public function __construct(
        private TeamCalendar $calendar,
        private TeamSafeBands $bands,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Team $team, User $athlete): array
    {
        /** @var Collection<int, Goal> $goals */
        $goals = Goal::query()
            ->where('user_id', $athlete->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'deadline', 'created_at']);

        return [
            'athlete' => [
                'id' => $athlete->id,
                'name' => $athlete->name,
                'status' => 'active',
            ],
            'timezone' => $this->calendar->timezone($team),
            'goals' => $goals->map(function (Goal $goal): array {
                $status = $goal->status->value;

                return [
                    'id' => $goal->id,
                    'name' => $goal->name,
                    'starts_on' => $goal->created_at?->toDateString(),
                    'ends_on' => $goal->deadline?->toDateString(),
                    'status' => $status,
                    'status_label' => $this->bands->goalStatusLabel($status),
                    'progress_percent' => $this->bands->goalProgressPercent($status),
                ];
            })->values()->all(),
        ];
    }
}
