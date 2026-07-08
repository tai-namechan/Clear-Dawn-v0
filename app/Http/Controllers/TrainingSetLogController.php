<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrainingSetLogs\StoreTrainingSetLogRequest;
use App\Http\Requests\TrainingSetLogs\UpdateTrainingSetLogRequest;
use App\Http\Resources\TrainingSetLogResource;
use App\Models\TrainingRunStep;
use App\Models\TrainingSetLog;
use App\Services\DeleteTrainingSetLogService;
use App\Services\RecordTrainingSetService;
use App\Services\UpdateTrainingSetLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TrainingSetLogController extends Controller
{
    public function store(
        StoreTrainingSetLogRequest $request,
        TrainingRunStep $trainingRunStep,
        RecordTrainingSetService $service,
    ): JsonResponse {
        Gate::authorize('record', $trainingRunStep->trainingRun);

        $setLog = $service->handle($trainingRunStep, $request->validated());

        return response()->json([
            'set_log' => TrainingSetLogResource::make($setLog)->resolve(),
        ]);
    }

    public function update(
        UpdateTrainingSetLogRequest $request,
        TrainingSetLog $trainingSetLog,
        UpdateTrainingSetLogService $service,
    ): JsonResponse {
        Gate::authorize('update', $trainingSetLog);

        $updated = $service->handle($trainingSetLog, $request->validated());

        return response()->json([
            'set_log' => TrainingSetLogResource::make($updated)->resolve(),
        ]);
    }

    public function destroy(TrainingSetLog $trainingSetLog, DeleteTrainingSetLogService $service): JsonResponse
    {
        Gate::authorize('delete', $trainingSetLog);

        $service->handle($trainingSetLog);

        return response()->json(['deleted' => true]);
    }
}
