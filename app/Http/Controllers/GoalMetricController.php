<?php

namespace App\Http\Controllers;

use App\Enums\GoalMetricDirection;
use App\Http\Requests\Goals\StoreGoalMetricRequest;
use App\Http\Requests\Goals\UpdateGoalMetricRequest;
use App\Models\Goal;
use App\Models\GoalMetric;
use App\Services\CreateGoalMetricService;
use App\Services\DeleteGoalMetricService;
use App\Services\UpdateGoalMetricService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class GoalMetricController extends Controller
{
    public function store(StoreGoalMetricRequest $request, Goal $goal, CreateGoalMetricService $service): RedirectResponse
    {
        Gate::authorize('update', $goal);

        $validated = $request->validated();

        $service->handle($goal, [
            'metric_id' => $validated['metric_id'],
            'baseline_value' => $validated['baseline_value'] ?? null,
            'target_value' => $validated['target_value'] ?? null,
            'target_low' => $validated['target_low'] ?? null,
            'target_high' => $validated['target_high'] ?? null,
            'direction' => isset($validated['direction']) ? GoalMetricDirection::from($validated['direction']) : null,
            'note' => $validated['note'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->route('goals.show', $goal);
    }

    public function update(
        UpdateGoalMetricRequest $request,
        GoalMetric $goalMetric,
        UpdateGoalMetricService $service,
    ): RedirectResponse {
        Gate::authorize('update', $goalMetric->goal);

        $validated = $request->validated();
        $reason = $validated['reason'];
        unset($validated['reason']);

        if (isset($validated['direction'])) {
            $validated['direction'] = GoalMetricDirection::from($validated['direction']);
        }

        $service->handle($goalMetric, $validated, $reason);

        return redirect()->route('goals.show', $goalMetric->goal);
    }

    public function destroy(GoalMetric $goalMetric, DeleteGoalMetricService $service): RedirectResponse
    {
        Gate::authorize('update', $goalMetric->goal);

        $goal = $goalMetric->goal;
        $service->handle($goalMetric);

        return redirect()->route('goals.show', $goal);
    }
}
