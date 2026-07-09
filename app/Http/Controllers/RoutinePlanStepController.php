<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoutinePlanSteps\ReorderRoutinePlanStepsRequest;
use App\Http\Requests\RoutinePlanSteps\StoreRoutinePlanStepRequest;
use App\Http\Requests\RoutinePlanSteps\UpdateRoutinePlanStepRequest;
use App\Http\Resources\RoutinePlanStepResource;
use App\Models\RoutinePlan;
use App\Models\RoutinePlanStep;
use App\Services\CreateRoutinePlanStepService;
use App\Services\DeleteRoutinePlanStepService;
use App\Services\ReorderRoutinePlanStepsService;
use App\Services\UpdateRoutinePlanStepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class RoutinePlanStepController extends Controller
{
    public function store(
        StoreRoutinePlanStepRequest $request,
        RoutinePlan $p,
        CreateRoutinePlanStepService $service,
    ): JsonResponse {
        Gate::authorize('updateSteps', $p);

        // Keep validated scalars as-is; Eloquent enum cast handles purpose.
        $step = $service->handle($p, $request->validated());

        return response()->json([
            'step' => RoutinePlanStepResource::make($step->load(['routineItem', 'video']))->resolve(),
        ]);
    }

    public function update(
        UpdateRoutinePlanStepRequest $request,
        RoutinePlan $p,
        RoutinePlanStep $step,
        UpdateRoutinePlanStepService $service,
    ): JsonResponse {
        Gate::authorize('updateSteps', $step->routinePlan);

        $updated = $service->handle($step, $request->validated());

        return response()->json([
            'step' => RoutinePlanStepResource::make($updated->load(['routineItem', 'video']))->resolve(),
        ]);
    }

    public function destroy(
        RoutinePlan $p,
        RoutinePlanStep $step,
        DeleteRoutinePlanStepService $service,
    ): JsonResponse {
        Gate::authorize('updateSteps', $step->routinePlan);

        $service->handle($step);

        return response()->json(['deleted' => true]);
    }

    public function reorder(
        ReorderRoutinePlanStepsRequest $request,
        RoutinePlan $p,
        ReorderRoutinePlanStepsService $service,
    ): RedirectResponse {
        Gate::authorize('updateSteps', $p);

        $service->handle($p, $request->validated()['ordered_ids']);

        return back();
    }
}
