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

        $validated = $request->validated();
        /** @var array{routine_item_id: string, video_id?: string|null, purpose?: StepPurpose|null, target_load?: float|string|null, load_unit?: string|null, target_amount?: float|string|null, amount_unit?: string|null, target_blocks?: int|null, rest_seconds?: int|null, note?: string|null} $attributes */
        $attributes = $this->mapStepAttributes($validated);

        $step = $service->handle($routine, $attributes);

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

        $validated = $request->validated();
        $attributes = $this->mapStepAttributes($validated, partial: true);

        $updated = $service->handle($routineStep, $attributes);

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
