<?php

namespace App\Http\Controllers;

use App\Enums\RoutinePlanStatus;
use App\Enums\VideoStatus;
use App\Http\Requests\RoutinePlans\StoreRoutinePlanRequest;
use App\Http\Requests\RoutinePlans\UpdateRoutinePlanRequest;
use App\Http\Resources\RoutineItemResource;
use App\Http\Resources\RoutinePlanResource;
use App\Http\Resources\VideoResource;
use App\Models\Routine;
use App\Models\RoutinePlan;
use App\Models\Video;
use App\Queries\GetRoutineItemsQuery;
use App\Services\CreateRoutinePlanService;
use App\Services\DeleteRoutinePlanService;
use App\Services\UpdateRoutinePlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoutinePlanController extends Controller
{
    public function show(
        Request $request,
        RoutinePlan $p,
        GetRoutineItemsQuery $routineItemsQuery,
    ): Response {
        Gate::authorize('view', $p);

        $user = $request->user();

        $p->load([
            'steps' => fn ($q) => $q->orderBy('sort_order'),
            'steps.routineItem',
            'steps.video',
            'sessions' => fn ($q) => $q->orderByDesc('started_at'),
        ]);

        $videos = Video::query()
            ->where('user_id', $user->id)
            ->where('status', VideoStatus::Ready)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return Inertia::render('Plans/Show', [
            'plan' => RoutinePlanResource::make($p)->resolve(),
            'routineItems' => RoutineItemResource::collection(
                $routineItemsQuery->handle($user),
            )->resolve(),
            'videos' => VideoResource::collection($videos)->resolve(),
        ]);
    }

    public function store(StoreRoutinePlanRequest $request, CreateRoutinePlanService $service): JsonResponse
    {
        /** @var array{title: string, scheduled_on: string, life_area_id?: string|null, routine_id?: string|null, note?: string|null} $validated */
        $validated = $request->validated();
        $user = $request->user();

        $routine = null;
        if (isset($validated['routine_id'])) {
            /** @var Routine $routine */
            $routine = Routine::query()
                ->where('user_id', $user->id)
                ->whereKey($validated['routine_id'])
                ->firstOrFail();
        }

        $plan = $service->handle($user, $validated, $routine);

        return response()->json([
            'plan' => RoutinePlanResource::make($plan)->resolve(),
        ]);
    }

    public function update(
        UpdateRoutinePlanRequest $request,
        RoutinePlan $p,
        UpdateRoutinePlanService $service,
    ): JsonResponse {
        Gate::authorize('update', $p);

        $validated = $request->validated();
        $attributes = $validated;

        if (isset($validated['status'])) {
            $attributes['status'] = RoutinePlanStatus::from($validated['status']);
        }

        $updated = $service->handle($p, $attributes);

        return response()->json([
            'plan' => RoutinePlanResource::make($updated)->resolve(),
        ]);
    }

    public function destroy(RoutinePlan $p, DeleteRoutinePlanService $service): JsonResponse
    {
        Gate::authorize('delete', $p);

        $service->handle($p);

        return response()->json(['deleted' => true]);
    }
}
