<?php

namespace App\Http\Controllers;

use App\Http\Requests\FoodItems\StoreFoodItemRequest;
use App\Http\Requests\FoodItems\UpdateFoodItemRequest;
use App\Http\Resources\FoodItemResource;
use App\Models\FoodItem;
use App\Queries\SearchFoodItemsQuery;
use App\Services\CreateFoodItemService;
use App\Services\DeleteFoodItemService;
use App\Services\UpdateFoodItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FoodItemController extends Controller
{
    public function index(Request $request, SearchFoodItemsQuery $query): Response|JsonResponse
    {
        $search = $request->string('query')->toString();
        $items = $query->handle($request->user(), $search !== '' ? $search : null);

        // JSON list for meal entry modal search (avoids Inertia asset-version 409)
        if ($request->wantsJson() && ! $request->headers->has('X-Inertia')) {
            return response()->json([
                'foods' => FoodItemResource::collection($items)->resolve(),
            ]);
        }

        return Inertia::render('Meals/Foods', [
            'foods' => FoodItemResource::collection($items)->resolve(),
            'query' => $search,
        ]);
    }

    public function store(StoreFoodItemRequest $request, CreateFoodItemService $service): JsonResponse
    {
        /** @var array{name: string, serving_label: string, kcal: float|int|string, protein_g: float|int|string, fat_g: float|int|string, carb_g: float|int|string} $validated */
        $validated = $request->validated();

        $food = $service->handle($request->user(), $validated);

        return response()->json([
            'food' => FoodItemResource::make($food)->resolve(),
        ], 201);
    }

    public function update(
        UpdateFoodItemRequest $request,
        FoodItem $foodItem,
        UpdateFoodItemService $service,
    ): JsonResponse {
        Gate::authorize('update', $foodItem);

        /** @var array{name: string, serving_label: string, kcal: float|int|string, protein_g: float|int|string, fat_g: float|int|string, carb_g: float|int|string} $validated */
        $validated = $request->validated();

        $food = $service->handle($foodItem, $validated);

        return response()->json([
            'food' => FoodItemResource::make($food)->resolve(),
        ]);
    }

    public function destroy(FoodItem $foodItem, DeleteFoodItemService $service): JsonResponse
    {
        Gate::authorize('delete', $foodItem);

        $service->handle($foodItem);

        return response()->json(['deleted' => true]);
    }
}
