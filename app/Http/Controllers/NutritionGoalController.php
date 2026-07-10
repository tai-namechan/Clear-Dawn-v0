<?php

namespace App\Http\Controllers;

use App\Http\Requests\NutritionGoals\UpsertNutritionGoalsRequest;
use App\Http\Resources\NutritionGoalResource;
use App\Services\UpsertNutritionGoalsService;
use Illuminate\Http\JsonResponse;

class NutritionGoalController extends Controller
{
    public function upsert(
        UpsertNutritionGoalsRequest $request,
        UpsertNutritionGoalsService $service,
    ): JsonResponse {
        /** @var array{kcal: float|int|string, protein_g: float|int|string, fat_g: float|int|string, carb_g: float|int|string} $validated */
        $validated = $request->validated();

        $goal = $service->handle($request->user(), $validated);

        return response()->json([
            'goal' => NutritionGoalResource::make($goal)->resolve(),
        ]);
    }
}
