<?php

namespace App\Http\Controllers;

use App\Enums\GoalMetricDirection;
use App\Http\Requests\Goals\StoreGoalMetricRequest;
use App\Http\Requests\Goals\UpdateGoalMetricRequest;
use App\Http\Resources\GoalMetricResource;
use App\Models\Goal;
use App\Models\GoalMetric;
use App\Services\CreateGoalMetricService;
use App\Services\DeleteGoalMetricService;
use App\Services\UpdateGoalMetricService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class GoalMetricController extends Controller
{
    public function store(StoreGoalMetricRequest $request, Goal $goal, CreateGoalMetricService $service): JsonResponse
    {
        Gate::authorize('update', $goal);

        $validated = $request->validated();

        $goalMetric = $service->handle($goal, [
            'metric_id' => $validated['metric_id'],
            'baseline_value' => $validated['baseline_value'] ?? null,
            'target_value' => $validated['target_value'] ?? null,
            'target_low' => $validated['target_low'] ?? null,
            'target_high' => $validated['target_high'] ?? null,
            'direction' => isset($validated['direction']) ? GoalMetricDirection::from($validated['direction']) : null,
            'note' => $validated['note'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'goal_metric' => GoalMetricResource::make($goalMetric->load('metric'))->resolve(),
        ]);
    }

    public function update(
        UpdateGoalMetricRequest $request,
        GoalMetric $goalMetric,
        UpdateGoalMetricService $service,
    ): JsonResponse {
        Gate::authorize('update', $goalMetric->goal);

        $validated = $request->validated();
        $reason = $validated['reason'];
        unset($validated['reason']);

        if (isset($validated['direction'])) {
            $validated['direction'] = GoalMetricDirection::from($validated['direction']);
        }

        $updated = $service->handle($goalMetric, $validated, $reason);

        return response()->json([
            'goal_metric' => GoalMetricResource::make($updated->load('metric'))->resolve(),
        ]);
    }

    public function destroy(GoalMetric $goalMetric, DeleteGoalMetricService $service): JsonResponse
    {
        Gate::authorize('update', $goalMetric->goal);

        $service->handle($goalMetric);

        return response()->json(['deleted' => true]);
    }
}
