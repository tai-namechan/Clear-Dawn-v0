<?php

namespace App\Queries;

use App\Models\Metric;
use App\Models\MetricRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetDailyMetricsQuery
{
    /**
     * 指定日の全メトリクス記録（未入力メトリクスは null value）を返す。
     *
     * @return Collection<int, array{
     *     metric: Metric,
     *     record: MetricRecord|null
     * }>
     */
    public function handle(User $user, Carbon $date): Collection
    {
        $metrics = Metric::query()->orderBy('sort_order')->get();

        $records = MetricRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('recorded_on', $date->toDateString())
            ->get()
            ->keyBy('metric_id');

        return $metrics->map(fn (Metric $metric): array => [
            'metric' => $metric,
            'record' => $records->get($metric->id),
        ]);
    }
}
