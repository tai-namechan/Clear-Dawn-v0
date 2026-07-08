<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoutinePlanResource;
use App\Queries\GetTodayQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    public function index(Request $request, GetTodayQuery $query): Response
    {
        $targetDate = Carbon::parse($request->input('date', now()->toDateString()));
        $plans = $query->handle($request->user(), $targetDate);

        return Inertia::render('Today/Index', [
            'date' => $targetDate->toDateString(),
            'plans' => RoutinePlanResource::collection($plans)->resolve(),
        ]);
    }
}
