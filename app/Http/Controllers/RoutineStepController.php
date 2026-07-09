<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoutineSteps\ReorderRoutineStepsRequest;
use App\Http\Requests\RoutineSteps\StoreRoutineStepRequest;
use App\Http\Requests\RoutineSteps\UpdateRoutineStepRequest;
use App\Http\Resources\RoutineStepResource;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Services\CreateRoutineStepService;
use App\Services\DeleteRoutineStepService;
use App\Services\ReorderRoutineStepsService;
use App\Services\UpdateRoutineStepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class RoutineStepController extends Controller
{
    public function store(
        StoreRoutineStepRequest $request,
        Routine $routine,
        CreateRoutineStepService $service,
    ): JsonResponse {
        Gate::authorize('update', $routine);

        // Keep validated scalars as-is; Eloquent enum cast handles purpose.
        $step = $service->handle($routine, $request->validated());

        return response()->json([
            'step' => RoutineStepResource::make($step->load(['routineItem', 'video']))->resolve(),
        ]);
    }

    public function update(
        UpdateRoutineStepRequest $request,
        Routine $routine,
        RoutineStep $routineStep,
        UpdateRoutineStepService $service,
    ): JsonResponse {
        Gate::authorize('update', $routineStep);

        $updated = $service->handle($routineStep, $request->validated());

        return response()->json([
            'step' => RoutineStepResource::make($updated->load(['routineItem', 'video']))->resolve(),
        ]);
    }

    public function destroy(
        Routine $routine,
        RoutineStep $routineStep,
        DeleteRoutineStepService $service,
    ): JsonResponse {
        Gate::authorize('delete', $routineStep);

        $service->handle($routineStep);

        return response()->json(['deleted' => true]);
    }

    public function reorder(
        ReorderRoutineStepsRequest $request,
        Routine $routine,
        ReorderRoutineStepsService $service,
    ): RedirectResponse {
        Gate::authorize('update', $routine);

        $service->handle($routine, $request->validated()['ordered_ids']);

        return back();
    }
}
