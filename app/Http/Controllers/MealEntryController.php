<?php

namespace App\Http\Controllers;

use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Http\Requests\MealEntries\CopyPreviousDayMealsRequest;
use App\Http\Requests\MealEntries\ShowMealsRequest;
use App\Http\Requests\MealEntries\StoreMealEntryRequest;
use App\Http\Requests\MealEntries\UpdateMealEntryRequest;
use App\Http\Resources\MealEntryResource;
use App\Http\Resources\NutritionGoalResource;
use App\Models\MealEntry;
use App\Queries\GetDailyMealsQuery;
use App\Queries\GetNutritionChartQuery;
use App\Services\CopyPreviousDayMealsService;
use App\Services\CreateMealEntryService;
use App\Services\DeleteMealEntryService;
use App\Services\UpdateMealEntryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MealEntryController extends Controller
{
    public function index(
        ShowMealsRequest $request,
        GetDailyMealsQuery $dailyQuery,
        GetNutritionChartQuery $chartQuery,
        UserTimezoneResolver $timezoneResolver,
    ): Response {
        $user = $request->user();
        $today = CarbonImmutable::now($timezoneResolver->for($user))->startOfDay();

        $date = Carbon::parse($request->validated('date') ?? $today->toDateString());
        $from = Carbon::parse($request->validated('from') ?? $today->subDays(29)->toDateString());
        $to = Carbon::parse($request->validated('to') ?? $today->toDateString());

        $daily = $dailyQuery->handle($user, $date);
        $chartPoints = $chartQuery->handle($user, $from, $to);

        return Inertia::render('Meals/Index', [
            'date' => $daily['date'],
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'sections' => array_map(fn (array $section): array => [
                'meal_type' => $section['meal_type'],
                'label' => $section['label'],
                'entries' => MealEntryResource::collection($section['entries'])->resolve(),
                'subtotal' => $section['subtotal'],
            ], $daily['sections']),
            'totals' => $daily['totals'],
            'goal' => $daily['goal'] !== null
                ? NutritionGoalResource::make($daily['goal'])->resolve()
                : null,
            'chartPoints' => $chartPoints->values()->all(),
        ]);
    }

    public function store(StoreMealEntryRequest $request, CreateMealEntryService $service): JsonResponse
    {
        /** @var array{eaten_on: string, meal_type: string, food_item_id?: string|null, name?: string, quantity: float|int|string, kcal?: float|int|string|null, protein_g?: float|int|string|null, fat_g?: float|int|string|null, carb_g?: float|int|string|null, note?: string|null, register_as_food?: bool} $validated */
        $validated = $request->validated();

        $entry = $service->handle($request->user(), $validated);

        return response()->json([
            'entry' => MealEntryResource::make($entry)->resolve(),
        ], 201);
    }

    public function update(
        UpdateMealEntryRequest $request,
        MealEntry $mealEntry,
        UpdateMealEntryService $service,
    ): JsonResponse {
        Gate::authorize('update', $mealEntry);

        /** @var array{eaten_on: string, meal_type: string, food_item_id?: string|null, name?: string, quantity: float|int|string, kcal?: float|int|string|null, protein_g?: float|int|string|null, fat_g?: float|int|string|null, carb_g?: float|int|string|null, note?: string|null} $validated */
        $validated = $request->validated();

        $entry = $service->handle($mealEntry, $validated);

        return response()->json([
            'entry' => MealEntryResource::make($entry)->resolve(),
        ]);
    }

    public function destroy(MealEntry $mealEntry, DeleteMealEntryService $service): JsonResponse
    {
        Gate::authorize('delete', $mealEntry);

        $service->handle($mealEntry);

        return response()->json(['deleted' => true]);
    }

    public function copyPreviousDay(
        CopyPreviousDayMealsRequest $request,
        CopyPreviousDayMealsService $service,
    ): JsonResponse {
        $result = $service->handle(
            $request->user(),
            Carbon::parse($request->validated('date')),
        );

        return response()->json($result);
    }
}
