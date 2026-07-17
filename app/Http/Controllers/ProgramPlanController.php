<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProgramPlans\SelectProgramChoiceRequest;
use App\Http\Requests\ProgramPlans\TodayAdjustRequest;
use App\Http\Resources\RoutinePlanResource;
use App\Models\RoutinePlan;
use App\Services\ApplyTodayPlanAdjustmentService;
use App\Services\GenerateProgramDayPlansService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ProgramPlanController extends Controller
{
    public function selectChoice(
        SelectProgramChoiceRequest $request,
        GenerateProgramDayPlansService $service,
    ): JsonResponse {
        $date = Carbon::parse($request->validated('date') ?? now()->toDateString());
        $plans = $service->handle($request->user(), $date, $request->validated('choice_option_id'));

        return response()->json([
            'plans' => RoutinePlanResource::collection($plans)->resolve(),
        ]);
    }

    public function todayAdjust(
        TodayAdjustRequest $request,
        RoutinePlan $p,
        ApplyTodayPlanAdjustmentService $service,
    ): JsonResponse {
        Gate::authorize('update', $p);

        $plan = $service->handle($p, $request->validated());

        return response()->json([
            'plan' => RoutinePlanResource::make($plan->load(['steps', 'sessions']))->resolve(),
        ]);
    }
}
