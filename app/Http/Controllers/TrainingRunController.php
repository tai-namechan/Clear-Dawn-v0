<?php

namespace App\Http\Controllers;

use App\Http\Resources\TrainingRunResource;
use App\Models\TrainingPlan;
use App\Models\TrainingRun;
use App\Queries\GetTrainingRunQuery;
use App\Services\AbortTrainingRunService;
use App\Services\CompleteTrainingRunService;
use App\Services\StartTrainingRunService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TrainingRunController extends Controller
{
    public function show(Request $request, TrainingRun $trainingRun, GetTrainingRunQuery $query): Response
    {
        Gate::authorize('view', $trainingRun);

        $run = $query->handle($request->user(), $trainingRun->id);

        return Inertia::render('Training/Run', [
            'run' => TrainingRunResource::make($run)->resolve(),
        ]);
    }

    public function start(
        Request $request,
        TrainingPlan $trainingPlan,
        StartTrainingRunService $service,
    ): JsonResponse {
        Gate::authorize('start', $trainingPlan);

        $run = $service->handle($request->user(), $trainingPlan);

        return response()->json([
            'run' => TrainingRunResource::make($run)->resolve(),
        ]);
    }

    public function complete(TrainingRun $trainingRun, CompleteTrainingRunService $service): JsonResponse
    {
        Gate::authorize('complete', $trainingRun);

        $run = $service->handle($trainingRun);

        return response()->json([
            'run' => TrainingRunResource::make($run)->resolve(),
        ]);
    }

    public function abort(TrainingRun $trainingRun, AbortTrainingRunService $service): JsonResponse
    {
        Gate::authorize('abort', $trainingRun);

        $run = $service->handle($trainingRun);

        return response()->json([
            'run' => TrainingRunResource::make($run)->resolve(),
        ]);
    }
}
