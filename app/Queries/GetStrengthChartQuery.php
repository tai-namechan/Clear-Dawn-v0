<?php

namespace App\Queries;

use App\Enums\ExerciseCategory;
use App\Enums\TrainingRunStatus;
use App\Models\TrainingSetLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetStrengthChartQuery
{
    /**
     * 筋力種目の重量推移（種目別・日別の最大重量）。
     *
     * @return Collection<int, array{
     *     date: string,
     *     exercise_name: string,
     *     max_weight_kg: string|null
     * }>
     */
    public function handle(User $user, Carbon $from, Carbon $to, ?string $exerciseId = null): Collection
    {
        $query = TrainingSetLog::query()
            ->select([
                DB::raw('DATE(training_runs.started_at) as date'),
                'training_run_steps.exercise_name',
                DB::raw('MAX(training_set_logs.weight_kg) as max_weight_kg'),
            ])
            ->join('training_run_steps', 'training_run_steps.id', '=', 'training_set_logs.training_run_step_id')
            ->join('training_runs', 'training_runs.id', '=', 'training_run_steps.training_run_id')
            ->join('exercises', 'exercises.id', '=', 'training_run_steps.exercise_id')
            ->where('training_runs.user_id', $user->id)
            ->whereNotNull('training_run_steps.exercise_id')
            ->where('training_runs.status', TrainingRunStatus::Completed)
            ->where('exercises.category', ExerciseCategory::Strength)
            ->whereDate('training_runs.started_at', '>=', $from->toDateString())
            ->whereDate('training_runs.started_at', '<=', $to->toDateString())
            ->whereNotNull('training_set_logs.weight_kg')
            ->when($exerciseId !== null, fn ($q) => $q->where('training_run_steps.exercise_id', $exerciseId))
            ->groupBy('date', 'training_run_steps.exercise_name')
            ->orderBy('date');

        /** @var Collection<int, object{date: string, exercise_name: string, max_weight_kg: string|null}> $rows */
        $rows = $query->get();

        return $rows->map(fn (object $row): array => [
            'date' => $row->date,
            'exercise_name' => $row->exercise_name,
            'max_weight_kg' => $row->max_weight_kg !== null ? (string) $row->max_weight_kg : null,
        ]);
    }
}
