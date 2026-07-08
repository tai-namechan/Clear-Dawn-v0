<?php

namespace App\Queries;

use App\Models\Metric;
use App\Models\MetricRecord;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class GetMetricHistoryQuery
{
    /**
     * @return LengthAwarePaginator<int, MetricRecord>
     */
    public function handle(
        User $user,
        Metric $metric,
        ?Carbon $from = null,
        ?Carbon $to = null,
        int $perPage = 30,
    ): LengthAwarePaginator {
        return MetricRecord::query()
            ->where('user_id', $user->id)
            ->where('metric_id', $metric->id)
            ->when($from !== null, fn ($query) => $query->whereDate('recorded_on', '>=', $from->toDateString()))
            ->when($to !== null, fn ($query) => $query->whereDate('recorded_on', '<=', $to->toDateString()))
            ->with('lifeArea')
            ->orderByDesc('recorded_on')
            ->paginate($perPage);
    }
}
