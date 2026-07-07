<?php

namespace App\Http\Controllers;

use App\Enums\StepPurpose;
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
use Illuminate\Support\Facades\Gate;

class RoutineStepController extends Controller
{
    public function store(
        StoreRoutineStepRequest $request,
        Routine $routine,
        CreateRoutineStepService $service,
    ): JsonResponse {
        Gate::authorize('update', $routine);

        $validated = $request->validated();
        $attributes = $this->mapStepAttributes($validated);

        $step = $service->handle($routine, $attributes);

        return response()->json([
            'step' => RoutineStepResource::make($step->load(['exercise', 'video']))->resolve(),
        ]);
    }

    public function update(
        UpdateRoutineStepRequest $request,
        RoutineStep $routineStep,
        UpdateRoutineStepService $service,
    ): JsonResponse {
        Gate::authorize('update', $routineStep);

        $validated = $request->validated();
        $attributes = $this->mapStepAttributes($validated, partial: true);

        $updated = $service->handle($routineStep, $attributes);

        return response()->json([
            'step' => RoutineStepResource::make($updated->load(['exercise', 'video']))->resolve(),
        ]);
    }

    public function destroy(RoutineStep $routineStep, DeleteRoutineStepService $service): JsonResponse
    {
        Gate::authorize('delete', $routineStep);

        $service->handle($routineStep);

        return response()->json(['deleted' => true]);
    }

    public function reorder(
        ReorderRoutineStepsRequest $request,
        Routine $routine,
        ReorderRoutineStepsService $service,
    ): JsonResponse {
        Gate::authorize('update', $routine);

        $service->handle($routine, $request->validated()['ordered_ids']);

        return response()->json(['reordered' => true]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mapStepAttributes(array $validated, bool $partial = false): array
    {
        $attributes = $validated;

        if (array_key_exists('purpose', $validated)) {
            $attributes['purpose'] = $validated['purpose'] !== null
                ? StepPurpose::from($validated['purpose'])
                : null;
        } elseif (! $partial) {
            $attributes['purpose'] = null;
        }

        return $attributes;
    }
}
