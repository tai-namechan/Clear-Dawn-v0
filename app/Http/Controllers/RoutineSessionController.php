<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoutineSessionResource;
use App\Models\RoutinePlan;
use App\Models\RoutineSession;
use App\Queries\GetRoutineSessionQuery;
use App\Services\AbortRoutineSessionService;
use App\Services\CompleteRoutineSessionService;
use App\Services\StartRoutineSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoutineSessionController extends Controller
{
    public function show(Request $request, RoutineSession $s, GetRoutineSessionQuery $query): Response
    {
        Gate::authorize('view', $s);

        $session = $query->handle($request->user(), $s->id);

        return Inertia::render('Sessions/Show', [
            'session' => RoutineSessionResource::make($session)->resolve(),
        ]);
    }

    public function start(
        Request $request,
        RoutinePlan $p,
        StartRoutineSessionService $service,
    ): JsonResponse {
        Gate::authorize('start', $p);

        $session = $service->handle($request->user(), $p);

        return response()->json([
            'session' => RoutineSessionResource::make($session)->resolve(),
        ]);
    }

    public function complete(RoutineSession $s, CompleteRoutineSessionService $service): JsonResponse
    {
        Gate::authorize('complete', $s);

        $session = $service->handle($s);

        return response()->json([
            'session' => RoutineSessionResource::make($session)->resolve(),
        ]);
    }

    public function abort(RoutineSession $s, AbortRoutineSessionService $service): JsonResponse
    {
        Gate::authorize('abort', $s);

        $session = $service->handle($s);

        return response()->json([
            'session' => RoutineSessionResource::make($session)->resolve(),
        ]);
    }
}
