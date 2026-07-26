<?php

namespace App\Queries;

use App\Models\DailyCheckin;
use App\Models\MealEntry;
use App\Models\RoutineSession;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamCalendar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeamReportQuery
{
    public function __construct(private TeamCalendar $calendar) {}

    /** @return array<string, mixed> */
    public function handle(Team $team, int $days = 7): array
    {
        $days = in_array($days, [7, 30], true) ? $days : 7;
        [$start, $end] = $this->calendar->dayWindow($team, $days);
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $threshold = $days >= 30 ? 10 : 3;

        $athleteIds = $team->memberships()
            ->where('member_type', 'user')
            ->where('role', 'athlete')
            ->where('status', 'active')
            ->pluck('member_id');

        /** @var Collection<int, User> $athletes */
        $athletes = User::query()
            ->select(['id', 'name'])
            ->whereIn('id', $athleteIds)
            ->orderBy('name')
            ->get();

        $ids = $athletes->pluck('id');

        $trainingByUser = RoutineSession::query()
            ->whereIn('user_id', $ids)
            ->where('started_at', '>=', $start)
            ->where('started_at', '<=', $end)
            ->select('user_id', DB::raw('count(*) as aggregate_count'))
            ->groupBy('user_id')
            ->pluck('aggregate_count', 'user_id');

        $mealDaysByUser = MealEntry::query()
            ->whereIn('user_id', $ids)
            ->whereDate('eaten_on', '>=', $startDate)
            ->whereDate('eaten_on', '<=', $endDate)
            ->select('user_id', DB::raw('count(distinct eaten_on) as aggregate_count'))
            ->groupBy('user_id')
            ->pluck('aggregate_count', 'user_id');

        $conditionDaysByUser = DailyCheckin::query()
            ->whereIn('user_id', $ids)
            ->whereDate('checked_on', '>=', $startDate)
            ->whereDate('checked_on', '<=', $endDate)
            ->select('user_id', DB::raw('count(*) as aggregate_count'))
            ->groupBy('user_id')
            ->pluck('aggregate_count', 'user_id');

        $rows = $athletes->map(function (User $athlete) use ($trainingByUser, $mealDaysByUser, $conditionDaysByUser, $threshold): array {
            $mealDays = (int) ($mealDaysByUser[$athlete->id] ?? 0);

            return [
                'id' => $athlete->id,
                'name' => $athlete->name,
                'training_sessions' => (int) ($trainingByUser[$athlete->id] ?? 0),
                'meal_record_days' => $mealDays,
                'condition_record_days' => (int) ($conditionDaysByUser[$athlete->id] ?? 0),
                'needs_follow_up' => $mealDays < $threshold,
            ];
        });

        return [
            'period' => [
                'days' => $days,
                'timezone' => $this->calendar->timezone($team),
                'from' => $startDate,
                'to' => $endDate,
                'follow_up_threshold_days' => $threshold,
            ],
            'summary' => [
                'athletes' => $rows->count(),
                'training_sessions' => $rows->sum('training_sessions'),
                'meal_record_days' => $rows->sum('meal_record_days'),
                'needs_follow_up' => $rows->where('needs_follow_up', true)->count(),
            ],
            'athletes' => $rows->values()->all(),
            'disclaimer' => 'このレポートは記録の集計であり、健康状態の診断ではありません。',
        ];
    }
}
