<?php

namespace App\Http\Controllers;

use App\Http\Requests\Routines\StoreRoutineRequest;
use App\Http\Requests\Routines\UpdateRoutineRequest;
use App\Http\Resources\RoutineEditorResource;
use App\Http\Resources\RoutineResource;
use App\Models\Routine;
use App\Queries\GetRoutineEditorQuery;
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

    public function show(Request $request, Routine $routine, GetRoutineEditorQuery $query): Response
    {
        Gate::authorize('view', $routine);

        $editor = $query->handle($request->user(), $routine->id);

        return Inertia::render('Routines/Show', [
            'routine' => RoutineEditorResource::make($editor)->resolve(),
        ]);
    }

    public function store(StoreRoutineRequest $request, CreateRoutineService $service): JsonResponse
    {
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
