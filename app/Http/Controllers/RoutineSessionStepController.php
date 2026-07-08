<?php

namespace App\Http\Controllers;

use App\Enums\RoutineSessionStepStatus;
use App\Http\Requests\RoutineSessionSteps\UpdateRoutineSessionStepRequest;
use App\Http\Resources\RoutineSessionStepResource;
use App\Models\RoutineSession;
use App\Models\RoutineSessionStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RoutineSessionStepController extends Controller
{
    public function update(
        UpdateRoutineSessionStepRequest $request,
        RoutineSession $s,
        RoutineSessionStep $ss,
    ): JsonResponse {
        Gate::authorize('record', $s);

        $validated = $request->validated();
        $attributes = $validated;

        if (isset($validated['status'])) {
            $attributes['status'] = RoutineSessionStepStatus::from($validated['status']);
        }

        $ss->update($attributes);

        return response()->json([
            'step' => RoutineSessionStepResource::make($ss->refresh()->load('blockLogs'))->resolve(),
        ]);
    }
}
