<?php

namespace App\Http\Controllers;

use App\Enums\GoalStatus;
use App\Http\Requests\Goals\StoreGoalRequest;
use App\Http\Requests\Goals\UpdateGoalRequest;
use App\Http\Resources\GoalResource;
use App\Http\Resources\MetricResource;
use App\Models\Goal;
use App\Models\Metric;
use App\Queries\GetGoalQuery;
use App\Queries\GetGoalsQuery;
use App\Services\CreateGoalService;
use App\Services\DeleteGoalService;
use App\Services\UpdateGoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GoalController extends Controller
{
    public function index(Request $request, GetGoalsQuery $query): Response
    {
        $goals = $query->handle($request->user());

        return Inertia::render('Goals/Index', [
            'goals' => GoalResource::collection($goals)->resolve(),
        ]);
    }

    public function show(Request $request, Goal $goal, GetGoalQuery $query): Response
    {
        Gate::authorize('view', $goal);

        $loaded = $query->handle($request->user(), $goal->id);

        $metrics = Metric::query()
            ->where(fn ($inner) => $inner->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Goals/Show', [
            'goal' => GoalResource::make($loaded)->resolve(),
            'availableMetrics' => MetricResource::collection($metrics)->resolve(),
        ]);
    }

    public function store(StoreGoalRequest $request, CreateGoalService $service): JsonResponse
    {
        $validated = $request->validated();

        $goal = $service->handle($request->user(), [
            'name' => $validated['name'],
            'why' => $validated['why'] ?? null,
            'parent_goal_id' => $validated['parent_goal_id'] ?? null,
            'matrix_cell_id' => $validated['matrix_cell_id'] ?? null,
            'priority' => $validated['priority'] ?? 0,
            'status' => isset($validated['status']) ? GoalStatus::from($validated['status']) : GoalStatus::Active,
            'deadline' => $validated['deadline'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'goal' => GoalResource::make($goal)->resolve(),
        ]);
    }

    public function update(UpdateGoalRequest $request, Goal $goal, UpdateGoalService $service): JsonResponse
    {
        Gate::authorize('update', $goal);

        $validated = $request->validated();
        $reason = $validated['reason'];
        unset($validated['reason']);

        if (isset($validated['status'])) {
            $validated['status'] = GoalStatus::from($validated['status']);
        }

        $updated = $service->handle($goal, $validated, $reason);

        return response()->json([
            'goal' => GoalResource::make($updated)->resolve(),
        ]);
    }

    public function destroy(Goal $goal, DeleteGoalService $service): JsonResponse
    {
        Gate::authorize('delete', $goal);

        $service->handle($goal);

        return response()->json(['deleted' => true]);
    }
}
