<?php

namespace App\Http\Controllers;

use App\Enums\VideoStatus;
use App\Http\Requests\Routines\StoreRoutineRequest;
use App\Http\Requests\Routines\UpdateRoutineRequest;
use App\Http\Resources\LifeAreaResource;
use App\Http\Resources\RoutineEditorResource;
use App\Http\Resources\RoutineItemResource;
use App\Http\Resources\RoutineResource;
use App\Http\Resources\VideoResource;
use App\Models\Routine;
use App\Models\Video;
use App\Queries\GetRoutineEditorQuery;
use App\Queries\GetRoutineItemsQuery;
use App\Queries\GetRoutinesQuery;
use App\Services\CreateRoutineService;
use App\Services\DeleteRoutineService;
use App\Services\UpdateRoutineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoutineController extends Controller
{
    public function index(Request $request, GetRoutinesQuery $query): Response
    {
        $routines = $query->handle($request->user());

        return Inertia::render('Routines/Index', [
            'routines' => RoutineResource::collection($routines)->resolve(),
        ]);
    }

    public function show(
        Request $request,
        Routine $routine,
        GetRoutineEditorQuery $query,
        GetRoutinesQuery $routinesQuery,
        GetRoutineItemsQuery $routineItemsQuery,
    ): Response {
        Gate::authorize('view', $routine);

        $user = $request->user();
        $editor = $query->handle($user, $routine->id);
        $lifeAreas = $user->lifeAreas()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $otherRoutines = $routinesQuery->handle($user)
            ->where('id', '!=', $routine->id)
            ->take(5)
            ->values();
        $routineItems = $routineItemsQuery->handle($user);
        $videos = Video::query()
            ->where('user_id', $user->id)
            ->where('status', VideoStatus::Ready)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return Inertia::render('Routines/Show', [
            'routine' => RoutineEditorResource::make($editor)->resolve(),
            'lifeAreas' => LifeAreaResource::collection($lifeAreas)->resolve(),
            'otherRoutines' => RoutineResource::collection($otherRoutines)->resolve(),
            'routineItems' => RoutineItemResource::collection($routineItems)->resolve(),
            'videos' => VideoResource::collection($videos)->resolve(),
        ]);
    }

    public function store(StoreRoutineRequest $request, CreateRoutineService $service): JsonResponse
    {
        /** @var array{name: string, life_area_id?: string|null, description?: string|null, is_active?: bool} $validated */
        $validated = $request->validated();

        $routine = $service->handle($request->user(), $validated);

        return response()->json([
            'routine' => RoutineResource::make($routine)->resolve(),
        ]);
    }

    public function update(
        UpdateRoutineRequest $request,
        Routine $routine,
        UpdateRoutineService $service,
    ): JsonResponse {
        Gate::authorize('update', $routine);

        $updated = $service->handle($routine, $request->validated());

        return response()->json([
            'routine' => RoutineResource::make($updated)->resolve(),
        ]);
    }

    public function destroy(Routine $routine, DeleteRoutineService $service): JsonResponse
    {
        Gate::authorize('delete', $routine);

        $service->handle($routine);

        return response()->json(['deleted' => true]);
    }
}
