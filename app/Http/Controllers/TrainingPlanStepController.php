<?php

namespace App\Http\Controllers;

use App\Enums\StepPurpose;
use App\Http\Requests\TrainingPlanSteps\ReorderTrainingPlanStepsRequest;
use App\Http\Requests\TrainingPlanSteps\StoreTrainingPlanStepRequest;
use App\Http\Requests\TrainingPlanSteps\UpdateTrainingPlanStepRequest;
use App\Http\Resources\TrainingPlanStepResource;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanStep;
use App\Services\CreateTrainingPlanStepService;
use App\Services\DeleteTrainingPlanStepService;
use App\Services\ReorderTrainingPlanStepsService;
use App\Services\UpdateTrainingPlanStepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TrainingPlanStepController extends Controller
{
    public function store(
        StoreTrainingPlanStepRequest $request,
        TrainingPlan $trainingPlan,
        CreateTrainingPlanStepService $service,
    ): JsonResponse {
        Gate::authorize('updateSteps', $trainingPlan);

        $validated = $request->validated();
        /** @var array{exercise_id: string, video_id?: string|null, purpose?: StepPurpose|null, target_sets?: int|null, target_reps?: int|null, target_weight_kg?: float|string|null, target_distance_m?: float|string|null, target_duration_seconds?: int|null, rest_seconds?: int|null, note?: string|null} $attributes */
        $attributes = $this->mapStepAttributes($validated);

        $step = $service->handle($trainingPlan, $attributes);

        return response()->json([
            'step' => TrainingPlanStepResource::make($step->load(['exercise', 'video']))->resolve(),
        ]);
    }

    public function update(
        UpdateTrainingPlanStepRequest $request,
        TrainingPlan $trainingPlan,
        TrainingPlanStep $trainingPlanStep,
        UpdateTrainingPlanStepService $service,
    ): JsonResponse {
        Gate::authorize('updateSteps', $trainingPlanStep->trainingPlan);

        $validated = $request->validated();
        $attributes = $this->mapStepAttributes($validated, partial: true);

        $updated = $service->handle($trainingPlanStep, $attributes);

        return response()->json([
            'step' => TrainingPlanStepResource::make($updated->load(['exercise', 'video']))->resolve(),
        ]);
    }

    public function destroy(
        TrainingPlan $trainingPlan,
        TrainingPlanStep $trainingPlanStep,
        DeleteTrainingPlanStepService $service,
    ): JsonResponse {
        Gate::authorize('updateSteps', $trainingPlanStep->trainingPlan);

        $service->handle($trainingPlanStep);

        return response()->json(['deleted' => true]);
    }

    public function reorder(
        ReorderTrainingPlanStepsRequest $request,
        TrainingPlan $trainingPlan,
        ReorderTrainingPlanStepsService $service,
    ): JsonResponse {
        Gate::authorize('updateSteps', $trainingPlan);

        $service->handle($trainingPlan, $request->validated()['ordered_ids']);

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
