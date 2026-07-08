<?php

namespace App\Http\Controllers;

use App\Enums\ExerciseCategory;
use App\Enums\TrackingType;
use App\Http\Requests\Exercises\StoreExerciseRequest;
use App\Http\Requests\Exercises\UpdateExerciseRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Queries\GetExercisesQuery;
use App\Services\CreateExerciseService;
use App\Services\DeleteExerciseService;
use App\Services\UpdateExerciseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExerciseController extends Controller
{
    public function index(Request $request, GetExercisesQuery $query): Response
    {
        $exercises = $query->handle($request->user());

        return Inertia::render('Exercises/Index', [
            'exercises' => ExerciseResource::collection($exercises)->resolve(),
        ]);
    }

    public function store(StoreExerciseRequest $request, CreateExerciseService $service): JsonResponse
    {
        $validated = $request->validated();

        $exercise = $service->handle($request->user(), [
            'name' => $validated['name'],
            'life_area_id' => $validated['life_area_id'] ?? null,
            'category' => ExerciseCategory::from($validated['category']),
            'tracking_type' => TrackingType::from($validated['tracking_type']),
            'note' => $validated['note'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'exercise' => ExerciseResource::make($exercise)->resolve(),
        ]);
    }

    public function update(
        UpdateExerciseRequest $request,
        Exercise $exercise,
        UpdateExerciseService $service,
    ): JsonResponse {
        Gate::authorize('update', $exercise);

        $validated = $request->validated();
        $attributes = $validated;

        if (isset($validated['category'])) {
            $attributes['category'] = ExerciseCategory::from($validated['category']);
        }

        if (isset($validated['tracking_type'])) {
            $attributes['tracking_type'] = TrackingType::from($validated['tracking_type']);
        }

        $updated = $service->handle($exercise, $attributes);

        return response()->json([
            'exercise' => ExerciseResource::make($updated)->resolve(),
        ]);
    }

    public function destroy(Exercise $exercise, DeleteExerciseService $service): JsonResponse
    {
        Gate::authorize('delete', $exercise);

        $service->handle($exercise);

        return response()->json(['deleted' => true]);
    }
}
