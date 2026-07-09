<?php

namespace App\Http\Controllers;

use App\Enums\StepPurpose;
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

        $validated = $request->validated();
        /** @var array{routine_item_id: string, video_id?: string|null, purpose?: StepPurpose|null, target_load?: float|string|null, load_unit?: string|null, target_amount?: float|string|null, amount_unit?: string|null, target_blocks?: int|null, rest_seconds?: int|null, note?: string|null} $attributes */
        $attributes = $this->mapStepAttributes($validated);

        $step = $service->handle($p, $attributes);

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

        $validated = $request->validated();
        $attributes = $this->mapStepAttributes($validated, partial: true);

        $updated = $service->handle($step, $attributes);

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

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mapStepAttributes(array $validated, bool $partial = false): array
    {
        $attributes = $validated;

        if (array_key_exists('purpose', $validated)) {
            $attributes['purpose'] = $this->normalizePurpose($validated['purpose']);
        } elseif (! $partial) {
            $attributes['purpose'] = null;
        }

        return $attributes;
    }

    private function normalizePurpose(mixed $purpose): ?StepPurpose
    {
        if ($purpose === null || $purpose === '') {
            return null;
        }

        if ($purpose instanceof StepPurpose) {
            return $purpose;
        }

        return StepPurpose::from((string) $purpose);
    }
}
