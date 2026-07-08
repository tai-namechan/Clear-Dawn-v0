<?php

namespace App\Http\Controllers;

use App\Enums\TrainingPlanStatus;
use App\Http\Requests\TrainingPlans\StoreTrainingPlanRequest;
use App\Http\Requests\TrainingPlans\UpdateTrainingPlanRequest;
use App\Http\Resources\TrainingPlanResource;
use App\Models\Routine;
use App\Models\TrainingPlan;
use App\Queries\GetTrainingDayQuery;
use App\Services\CreateTrainingPlanService;
use App\Services\DeleteTrainingPlanService;
use App\Services\UpdateTrainingPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TrainingPlanController extends Controller
{
    public function index(Request $request, GetTrainingDayQuery $query): Response
    {
        $targetDate = Carbon::parse($request->input('date', now()->toDateString()));
        $plans = $query->handle($request->user(), $targetDate);

        return Inertia::render('Training/Index', [
            'date' => $targetDate->toDateString(),
            'plans' => TrainingPlanResource::collection($plans)->resolve(),
        ]);
    }

    public function show(Request $request, TrainingPlan $trainingPlan): Response
    {
        Gate::authorize('view', $trainingPlan);

        $trainingPlan->load(['steps' => fn ($q) => $q->orderBy('sort_order'), 'steps.exercise', 'steps.video']);

        return Inertia::render('Training/PlanEdit', [
            'plan' => TrainingPlanResource::make($trainingPlan)->resolve(),
        ]);
    }

    public function day(Request $request, GetTrainingDayQuery $query, ?string $date = null): Response
    {
        $targetDate = Carbon::parse($date ?? now()->toDateString());
        $plans = $query->handle($request->user(), $targetDate);

        return Inertia::render('Training/Day', [
            'date' => $targetDate->toDateString(),
            'plans' => TrainingPlanResource::collection($plans)->resolve(),
        ]);
    }

    public function store(StoreTrainingPlanRequest $request, CreateTrainingPlanService $service): JsonResponse
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
            'plan' => TrainingPlanResource::make($plan)->resolve(),
        ]);
    }

    public function update(
        UpdateTrainingPlanRequest $request,
        TrainingPlan $trainingPlan,
        UpdateTrainingPlanService $service,
    ): JsonResponse {
        Gate::authorize('update', $trainingPlan);

        $validated = $request->validated();
        $attributes = $validated;

        if (isset($validated['status'])) {
            $attributes['status'] = TrainingPlanStatus::from($validated['status']);
        }

        $updated = $service->handle($trainingPlan, $attributes);

        return response()->json([
            'plan' => TrainingPlanResource::make($updated)->resolve(),
        ]);
    }

    public function destroy(TrainingPlan $trainingPlan, DeleteTrainingPlanService $service): JsonResponse
    {
        Gate::authorize('delete', $trainingPlan);

        $service->handle($trainingPlan);

        return response()->json(['deleted' => true]);
    }
}
