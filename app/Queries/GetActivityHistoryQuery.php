<?php

namespace App\Queries;

use App\Enums\ActivityLogEventType;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class GetActivityHistoryQuery
{
    /**
     * @param  array{
     *     event_type?: ActivityLogEventType|null,
     *     from?: Carbon|string|null,
     *     to?: Carbon|string|null
     * }  $filters
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    public function handle(User $user, array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return ActivityLog::query()
            ->where('user_id', $user->id)
            ->when(
                isset($filters['event_type']),
                fn ($query) => $query->where('event_type', $filters['event_type']),
            )
            ->when(
                isset($filters['from']),
                fn ($query) => $query->where('occurred_at', '>=', Carbon::parse($filters['from'])->startOfDay()),
            )
            ->when(
                isset($filters['to']),
                fn ($query) => $query->where('occurred_at', '<=', Carbon::parse($filters['to'])->endOfDay()),
            )
            ->with(['subject'])
            ->orderByDesc('occurred_at')
            ->paginate($perPage);
    }
}
