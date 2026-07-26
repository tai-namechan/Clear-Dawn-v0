<?php

namespace App\Queries;

use App\Models\RoutineSession;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamCalendar;
use App\Support\TeamSafeBands;
use Illuminate\Support\Collection;

class TeamAthleteTrainingQuery
{
    public function __construct(
        private TeamCalendar $calendar,
        private TeamSafeBands $bands,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Team $team, User $athlete, int $days = 30): array
    {
        [$start, $end] = $this->calendar->dayWindow($team, $days);

        /** @var Collection<int, RoutineSession> $sessions */
        $sessions = RoutineSession::query()
            ->where('user_id', $athlete->id)
            ->where('started_at', '>=', $start)
            ->where('started_at', '<=', $end)
            ->with(['routinePlan:id,title'])
            ->orderByDesc('started_at')
            ->get(['id', 'routine_plan_id', 'status', 'started_at', 'finished_at', 'session_rpe']);

        return [
            'athlete' => [
                'id' => $athlete->id,
                'name' => $athlete->name,
                'status' => 'active',
            ],
            'period' => [
                'days' => $days,
                'timezone' => $this->calendar->timezone($team),
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
            'sessions' => $sessions->map(function (RoutineSession $session) use ($team): array {
                $durationMinutes = null;
                if ($session->finished_at !== null) {
                    $durationMinutes = (int) $session->started_at
                        ->timezone($this->calendar->timezone($team))
                        ->diffInMinutes($session->finished_at->timezone($this->calendar->timezone($team)));
                }

                $status = $session->status->value;

                return [
                    'id' => $session->id,
                    'date' => $session->started_at->timezone($this->calendar->timezone($team))->toDateString(),
                    'category' => $session->routinePlan?->title ?: '練習',
                    'duration_minutes' => $durationMinutes,
                    'intensity' => $this->bands->intensityLabel(
                        $session->session_rpe !== null ? (float) $session->session_rpe : null,
                    ),
                    'status' => $status,
                    'status_label' => match ($status) {
                        'completed' => '完了',
                        'aborted' => '中断',
                        default => '実施中',
                    },
                    'summary' => match ($status) {
                        'completed' => 'セッション完了',
                        'aborted' => 'セッション中断',
                        default => 'セッション実施中',
                    },
                ];
            })->values()->all(),
        ];
    }
}
