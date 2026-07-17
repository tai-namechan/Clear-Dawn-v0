<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoutinePlanResource;
use App\Queries\GetTodayOpsQuery;
use App\Queries\GetTodayQuery;
use App\Services\EvaluateRulesForDayService;
use App\Services\GenerateProgramDayPlansService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    public function index(
        Request $request,
        GetTodayQuery $query,
        GetTodayOpsQuery $opsQuery,
        GenerateProgramDayPlansService $generateProgramDayPlans,
        EvaluateRulesForDayService $evaluateRules,
    ): Response {
        $targetDate = Carbon::parse($request->input('date', now()->toDateString()));
        $user = $request->user();

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
