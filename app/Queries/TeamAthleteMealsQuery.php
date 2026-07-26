<?php

namespace App\Queries;

use App\Models\MealEntry;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamCalendar;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class TeamAthleteMealsQuery
{
    public function __construct(private TeamCalendar $calendar) {}

    /** @return array<string, mixed> */
    public function handle(Team $team, User $athlete, int $days = 14): array
    {
        [$start, $end] = $this->calendar->dayWindow($team, $days);
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        /** @var Collection<int, object{eaten_on: mixed, meal_count: int}> $rows */
        $rows = MealEntry::query()
            ->where('user_id', $athlete->id)
            ->whereDate('eaten_on', '>=', $startDate)
            ->whereDate('eaten_on', '<=', $endDate)
            ->selectRaw('eaten_on, count(*) as meal_count')
            ->groupBy('eaten_on')
            ->orderByDesc('eaten_on')
            ->get();

        $byDate = $rows->keyBy(function (object $row): string {
            $value = $row->eaten_on;

            if ($value instanceof CarbonInterface) {
                return $value->toDateString();
            }

            return substr((string) $value, 0, 10);
        });
        $daysPayload = [];
        $cursor = $end->startOfDay();
        $streak = 0;
        $countingStreak = true;

        while ($cursor->toDateString() >= $startDate) {
            $date = $cursor->toDateString();
            $mealCount = (int) ($byDate->get($date)?->meal_count ?? 0);
            $hasRecord = $mealCount > 0;
            $daysPayload[] = [
                'date' => $date,
                'has_record' => $hasRecord,
                'meal_count' => $mealCount,
            ];

            if ($countingStreak) {
                if ($hasRecord) {
                    $streak++;
                } else {
                    $countingStreak = false;
                }
            }

            $cursor = $cursor->subDay();
        }

        $recordedDays = collect($daysPayload)->where('has_record', true)->count();
        $totalMeals = collect($daysPayload)->sum('meal_count');

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
            'summary' => [
                'recorded_days' => $recordedDays,
                'total_meals' => $totalMeals,
                'average_meals_per_recorded_day' => $recordedDays > 0
                    ? round($totalMeals / $recordedDays, 1)
                    : 0,
                'current_streak_days' => $streak,
            ],
            'days' => $daysPayload,
        ];
    }
}
