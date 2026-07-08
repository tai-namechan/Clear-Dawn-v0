<?php

namespace App\Queries;

use App\Models\Metric;
use App\Models\MetricRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetMetricChartQuery
{
    /**
     * 期間内のメトリクス推移（グラフ用）。日付昇順。
     *
     * @return Collection<int, array{date: string, value: string}>
     */
    public function handle(User $user, Metric $metric, Carbon $from, Carbon $to): Collection
    {
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
}
