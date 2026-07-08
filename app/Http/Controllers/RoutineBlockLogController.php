<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoutineBlockLogs\StoreRoutineBlockLogRequest;
use App\Http\Requests\RoutineBlockLogs\UpdateRoutineBlockLogRequest;
use App\Http\Resources\RoutineBlockLogResource;
use App\Models\RoutineBlockLog;
use App\Models\RoutineSessionStep;
use App\Services\DeleteRoutineBlockLogService;
use App\Services\RecordRoutineBlockService;
use App\Services\UpdateRoutineBlockLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RoutineBlockLogController extends Controller
{
    public function store(
        StoreRoutineBlockLogRequest $request,
        RoutineSessionStep $ss,
        RecordRoutineBlockService $service,
    ): JsonResponse {
        Gate::authorize('record', $ss->routineSession);

        $blockLog = $service->handle($ss, $request->validated());

        return response()->json([
            'block_log' => RoutineBlockLogResource::make($blockLog)->resolve(),
        ]);
    }

    public function update(
        UpdateRoutineBlockLogRequest $request,
        RoutineBlockLog $bl,
        UpdateRoutineBlockLogService $service,
    ): JsonResponse {
        Gate::authorize('update', $bl);

        $updated = $service->handle($bl, $request->validated());

        return response()->json([
            'block_log' => RoutineBlockLogResource::make($updated)->resolve(),
        ]);
    }

    public function destroy(RoutineBlockLog $bl, DeleteRoutineBlockLogService $service): JsonResponse
    {
        Gate::authorize('delete', $bl);

        $service->handle($bl);

        return response()->json(['deleted' => true]);
    }
}
