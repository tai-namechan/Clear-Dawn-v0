<?php

namespace App\Http\Controllers;

use App\Enums\TrainingRunStepStatus;
use App\Http\Requests\TrainingRunSteps\UpdateTrainingRunStepRequest;
use App\Http\Resources\TrainingRunStepResource;
use App\Models\TrainingRunStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TrainingRunStepController extends Controller
{
    public function update(
        UpdateTrainingRunStepRequest $request,
        TrainingRunStep $trainingRunStep,
    ): JsonResponse {
        Gate::authorize('record', $trainingRunStep->trainingRun);

        $validated = $request->validated();
        $attributes = $validated;

        if (isset($validated['status'])) {
            $attributes['status'] = TrainingRunStepStatus::from($validated['status']);
        }

        $trainingRunStep->update($attributes);

        return response()->json([
            'step' => TrainingRunStepResource::make($trainingRunStep->refresh()->load('setLogs'))->resolve(),
        ]);
    }
}
