<?php

namespace App\Queries;

use App\Models\Metric;
use App\Models\MetricRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GetMetricChartQuery
{
    public const GRANULARITY_DAY = 'day';

    public const GRANULARITY_WEEK = 'week';

    /**
     * 期間内のメトリクス推移（グラフ用）。日付昇順。
     *
     * @param  'day'|'week'  $granularity
     * @return Collection<int, array{date: string, value: string}>
     */
    public function handle(
        User $user,
        Metric $metric,
        Carbon $from,
        Carbon $to,
        string $granularity = self::GRANULARITY_DAY,
    ): Collection {
        if (! in_array($granularity, [self::GRANULARITY_DAY, self::GRANULARITY_WEEK], true)) {
            throw new InvalidArgumentException("Unsupported chart granularity [{$granularity}].");
        }

        if ($granularity === self::GRANULARITY_WEEK) {
            return $this->weeklyAverages($user, $metric, $from, $to);
        }

        return MetricRecord::query()
            ->where('user_id', $user->id)
            ->where('metric_id', $metric->id)
            ->whereDate('recorded_on', '>=', $from->toDateString())
            ->whereDate('recorded_on', '<=', $to->toDateString())
            ->orderBy('recorded_on')
            ->get(['recorded_on', 'value'])
            ->map(fn (MetricRecord $record): array => [
                'date' => $record->recorded_on->toDateString(),
                'value' => (string) $record->value,
            ]);
    }

    /**
     * @return Collection<int, array{date: string, value: string}>
     */
    private function weeklyAverages(User $user, Metric $metric, Carbon $from, Carbon $to): Collection
    {
        $weekStart = $this->weekStartExpression('recorded_on');

        $rows = MetricRecord::query()
            ->where('user_id', $user->id)
            ->where('metric_id', $metric->id)
            ->whereDate('recorded_on', '>=', $from->toDateString())
            ->whereDate('recorded_on', '<=', $to->toDateString())
            ->selectRaw("{$weekStart} as week_start, AVG(value) as avg_value")
            ->groupBy(DB::raw($weekStart))
            ->orderBy('week_start')
            ->toBase()
            ->get();

        return $rows->map(fn (object $row): array => [
            'date' => Carbon::parse((string) $row->week_start)->toDateString(),
            'value' => number_format((float) $row->avg_value, 2, '.', ''),
        ])->values();
    }

    /**
     * Monday-based week start expression (SQLite / MySQL).
     */
    private function weekStartExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "date({$column}, '-' || ((cast(strftime('%w', {$column}) as integer) + 6) % 7) || ' days')",
            default => "DATE_SUB({$column}, INTERVAL WEEKDAY({$column}) DAY)",
        };
    }
}
