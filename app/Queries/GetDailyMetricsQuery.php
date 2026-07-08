<?php

namespace App\Queries;

use App\Models\Metric;
use App\Models\MetricRecord;
use App\Models\User;
use Illuminate\Support\Carbon;

class GetDailyMetricsQuery
{
    /**
     * 指定日の全メトリクス記録（未入力メトリクスは null value）を返す。
     *
     * @return array<int, array{metric: Metric, record: MetricRecord|null}>
     */
    public function handle(User $user, Carbon $date): array
    {
        $records = MetricRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('recorded_on', $date->toDateString())
            ->get()
            ->keyBy('metric_id');

        return Metric::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Metric $metric): array => [
                'metric' => $metric,
                'record' => $records->get($metric->id),
            ])
            ->values()
            ->all();
    }
}
