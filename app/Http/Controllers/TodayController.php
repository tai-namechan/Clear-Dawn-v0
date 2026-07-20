<?php

namespace App\Http\Controllers;

use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Http\Requests\Today\ShowTodayRequest;
use App\Http\Resources\RoutinePlanResource;
use App\Queries\GetTodayOpsQuery;
use App\Queries\GetTodayQuery;
use App\Services\EvaluateRulesForDayService;
use App\Services\GenerateProgramDayPlansService;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    public function index(
        ShowTodayRequest $request,
        GetTodayQuery $query,
        GetTodayOpsQuery $opsQuery,
        GenerateProgramDayPlansService $generateProgramDayPlans,
        EvaluateRulesForDayService $evaluateRules,
        UserTimezoneResolver $timezoneResolver,
    ): Response {
        $user = $request->user();
        $today = $timezoneResolver->todayDateString($user);
        $targetDate = Carbon::parse($request->validated('date') ?? $today);

        $generateProgramDayPlans->handle($user, $targetDate);
        $evaluateRules->handle($user, $targetDate);

        $plans = $query->handle($user, $targetDate);
        $ops = $opsQuery->handle($user, $targetDate);

        return Inertia::render('Today/Index', [
            'date' => $targetDate->toDateString(),
            'plans' => RoutinePlanResource::collection($plans)->resolve(),
            'ops' => $ops,
        ]);
    }
}
