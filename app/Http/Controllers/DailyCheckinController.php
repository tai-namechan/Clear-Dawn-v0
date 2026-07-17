<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailyCheckins\UpsertDailyCheckinRequest;
use App\Services\ComputeDailyResourceStatesService;
use App\Services\EvaluateRulesForDayService;
use App\Services\UpsertDailyCheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DailyCheckinController extends Controller
{
    public function upsert(
        UpsertDailyCheckinRequest $request,
        UpsertDailyCheckinService $service,
        ComputeDailyResourceStatesService $computeResourceStates,
        EvaluateRulesForDayService $evaluateRules,
    ): JsonResponse {
        $date = Carbon::parse($request->validated('checked_on') ?? now()->toDateString());
        $user = $request->user();

        $checkin = $service->handle($user, $date, $request->validated());
        $states = $computeResourceStates->handle($user, $date);
        $evaluateRules->handle($user, $date);

        return response()->json([
            'checkin' => [
                'id' => $checkin->id,
                'checked_on' => $checkin->checked_on->toDateString(),
                'sleep_quality' => $checkin->sleep_quality,
                'fatigue' => $checkin->fatigue,
                'muscle_soreness' => $checkin->muscle_soreness,
                'stress' => $checkin->stress,
                'mood' => $checkin->mood,
                'region_tension' => $checkin->region_tension,
                'readiness_self' => $checkin->readiness_self,
                'note' => $checkin->note,
            ],
            'resource_states_count' => $states->count(),
        ]);
    }
}
