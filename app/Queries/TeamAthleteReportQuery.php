<?php

namespace App\Queries;

use App\Models\DailyCheckin;
use App\Models\MealEntry;
use App\Models\RoutineSession;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamCalendar;
use App\Support\TeamSafeBands;

class TeamAthleteReportQuery
{
    public function __construct(
        private TeamCalendar $calendar,
        private TeamSafeBands $bands,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Team $team, User $athlete): array
    {
        return [
            'athlete' => [
                'id' => $athlete->id,
                'name' => $athlete->name,
                'status' => 'active',
            ],
            'windows' => [
                $this->window($team, $athlete, 7),
                $this->window($team, $athlete, 30),
            ],
            'disclaimer' => 'このレポートは記録の集計であり、健康状態の診断ではありません。',
        ];
    }

    /** @return array<string, mixed> */
    private function window(Team $team, User $athlete, int $days): array
    {
        [$start, $end] = $this->calendar->dayWindow($team, $days);
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $trainingCount = RoutineSession::query()
            ->where('user_id', $athlete->id)
            ->where('started_at', '>=', $start)
            ->where('started_at', '<=', $end)
            ->count();

        $completedCount = RoutineSession::query()
            ->where('user_id', $athlete->id)
            ->where('started_at', '>=', $start)
            ->where('started_at', '<=', $end)
            ->where('status', 'completed')
            ->count();

        $mealDays = MealEntry::query()
            ->where('user_id', $athlete->id)
            ->whereDate('eaten_on', '>=', $startDate)
            ->whereDate('eaten_on', '<=', $endDate)
            ->distinct()
            ->count('eaten_on');

        $conditionDays = DailyCheckin::query()
            ->where('user_id', $athlete->id)
            ->whereDate('checked_on', '>=', $startDate)
            ->whereDate('checked_on', '<=', $endDate)
            ->count();

        $latestCheckin = DailyCheckin::query()
            ->where('user_id', $athlete->id)
            ->whereDate('checked_on', '>=', $startDate)
            ->whereDate('checked_on', '<=', $endDate)
            ->latest('checked_on')
            ->first(['readiness_self', 'fatigue']);

        $threshold = $days >= 30 ? 10 : 3;

        return [
            'days' => $days,
            'timezone' => $this->calendar->timezone($team),
            'from' => $startDate,
            'to' => $endDate,
            'training' => [
                'sessions' => $trainingCount,
                'completed_sessions' => $completedCount,
                'trend_label' => $trainingCount === 0
                    ? '実施記録なし'
                    : ($completedCount >= max(1, (int) floor($days / 7)) ? '継続的' : '少なめ'),
            ],
            'meals' => [
                'recorded_days' => $mealDays,
                'continuity_label' => $mealDays === 0
                    ? '記録なし'
                    : ($mealDays >= $threshold ? '継続良好' : '確認推奨'),
                'needs_follow_up' => $mealDays < $threshold,
            ],
            'condition' => [
                'recorded_days' => $conditionDays,
                'latest_recovery_level' => $this->bands->recoveryLabel(
                    $latestCheckin?->readiness_self,
                    $latestCheckin?->fatigue,
                ),
                'trend_label' => $conditionDays === 0
                    ? '記録なし'
                    : ($conditionDays >= max(1, (int) floor($days / 3)) ? '記録が安定' : '記録が少なめ'),
            ],
        ];
    }
}
