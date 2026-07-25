<?php

namespace App\Http\Controllers;

use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Enums\VideoStatus;
use App\Http\Requests\Routines\StoreRoutineRequest;
use App\Http\Requests\Routines\UpdateRoutineRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\LifeAreaResource;
use App\Http\Resources\RoutineEditorResource;
use App\Http\Resources\RoutineItemResource;
use App\Http\Resources\RoutinePlanResource;
use App\Http\Resources\RoutineResource;
use App\Http\Resources\VideoResource;
use App\Models\Routine;
use App\Models\Video;
use App\Queries\GetActivityHistoryQuery;
use App\Queries\GetRoutineEditorQuery;
use App\Queries\GetRoutineItemsQuery;
use App\Queries\GetRoutinesQuery;
use App\Queries\GetTodayOpsQuery;
use App\Queries\GetTodayQuery;
use App\Services\CreateRoutineService;
use App\Services\DeleteRoutineService;
use App\Services\UpdateRoutineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoutineController extends Controller
{
    public function index(
        Request $request,
        GetRoutinesQuery $query,
        GetTodayQuery $todayQuery,
        GetTodayOpsQuery $opsQuery,
        GetActivityHistoryQuery $historyQuery,
        UserTimezoneResolver $timezoneResolver,
    ): Response {
        $user = $request->user();
        $date = Carbon::parse($timezoneResolver->todayDateString($user));
        $routines = $query->handle($user);
        $plans = $todayQuery->handle($user, $date);
        $ops = $opsQuery->handle($user, $date);
        $history = $historyQuery->handle($user, [], 8);

        $tab = match ($request->query('tab')) {
            'menu' => 'menu',
            'history' => 'history',
            default => 'today',
        };

        return Inertia::render('Routines/Index', [
            'date' => $date->toDateString(),
            'tab' => $tab,
            'plans' => RoutinePlanResource::collection($plans)->resolve(),
            'routines' => RoutineResource::collection($routines)->resolve(),
            'ops' => $ops,
            'history' => ActivityLogResource::collection($history->items())->resolve(),
        ]);
    }

    public function create(Request $request, GetRoutinesQuery $routinesQuery): Response
    {
        $user = $request->user();
        $lifeAreas = $user->lifeAreas()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $otherRoutines = $routinesQuery->handle($user)->take(5)->values();

        return Inertia::render('Routines/Show', [
            'routine' => [
                'id' => null,
                'name' => '',
                'description' => null,
                'is_active' => true,
                'sort_order' => 0,
                'life_area_id' => null,
                'life_area' => null,
                'steps' => [],
                'steps_count' => 0,
                'created_at' => null,
            ],
            'lifeAreas' => LifeAreaResource::collection($lifeAreas)->resolve(),
            'otherRoutines' => RoutineResource::collection($otherRoutines)->resolve(),
            'routineItems' => [],
            'videos' => [],
            'isCreating' => true,
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
            'isCreating' => false,
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
