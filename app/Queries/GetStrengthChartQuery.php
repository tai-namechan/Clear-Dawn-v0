<?php

namespace App\Queries;

use App\Enums\RoutineItemCategory;
use App\Enums\RoutineSessionStatus;
use App\Models\RoutineBlockLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GetStrengthChartQuery
{
    /**
     * 筋力種目の負荷推移（種目別・日別の最大負荷）。
     *
     * @return array<int, array{date: string, item_name: string, max_load_value: string|null}>
     */
    public function handle(User $user, Carbon $from, Carbon $to, ?string $routineItemId = null): array
    {
        $query = RoutineBlockLog::query()
            ->select([
                DB::raw('DATE(routine_sessions.started_at) as date'),
                'routine_session_steps.item_name',
                DB::raw('MAX(routine_block_logs.load_value) as max_load_value'),
            ])
            ->join('routine_session_steps', 'routine_session_steps.id', '=', 'routine_block_logs.routine_session_step_id')
            ->join('routine_sessions', 'routine_sessions.id', '=', 'routine_session_steps.routine_session_id')
            ->join('routine_items', 'routine_items.id', '=', 'routine_session_steps.routine_item_id')
            ->where('routine_sessions.user_id', $user->id)
            ->whereNotNull('routine_session_steps.routine_item_id')
            ->where('routine_sessions.status', RoutineSessionStatus::Completed)
            ->where('routine_items.category', RoutineItemCategory::Strength)
            ->whereDate('routine_sessions.started_at', '>=', $from->toDateString())
            ->whereDate('routine_sessions.started_at', '<=', $to->toDateString())
            ->whereNotNull('routine_block_logs.load_value')
            ->when($routineItemId !== null, fn ($q) => $q->where('routine_session_steps.routine_item_id', $routineItemId))
            ->groupBy('date', 'routine_session_steps.item_name')
            ->orderBy('date');

        return $query->toBase()->get()->map(fn (object $row): array => [
            'date' => (string) $row->date,
            'item_name' => (string) $row->item_name,
            'max_load_value' => $row->max_load_value !== null
                ? number_format((float) $row->max_load_value, 2, '.', '')
                : null,
        ])->values()->all();
    }
}
