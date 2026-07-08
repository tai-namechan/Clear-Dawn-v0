<?php

namespace App\Http\Controllers;

use App\Enums\RoutineItemCategory;
use App\Enums\TrackingType;
use App\Http\Requests\RoutineItems\StoreRoutineItemRequest;
use App\Http\Requests\RoutineItems\UpdateRoutineItemRequest;
use App\Http\Resources\RoutineItemResource;
use App\Models\RoutineItem;
use App\Queries\GetRoutineItemQuery;
use App\Queries\GetRoutineItemsQuery;
use App\Services\CreateRoutineItemService;
use App\Services\DeleteRoutineItemService;
use App\Services\UpdateRoutineItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoutineItemController extends Controller
{
    public function index(Request $request, GetRoutineItemsQuery $query): Response
    {
        $routineItems = $query->handle($request->user());

        return Inertia::render('RoutineItems/Index', [
            'routineItems' => RoutineItemResource::collection($routineItems)->resolve(),
        ]);
    }

    public function show(Request $request, RoutineItem $item, GetRoutineItemQuery $query): Response
    {
        Gate::authorize('view', $item);

        $routineItem = $query->handle($request->user(), $item->id);

        return Inertia::render('RoutineItems/Show', [
            'routineItem' => RoutineItemResource::make($routineItem)->resolve(),
        ]);
    }

    public function store(StoreRoutineItemRequest $request, CreateRoutineItemService $service): JsonResponse
    {
        $validated = $request->validated();

        $routineItem = $service->handle($request->user(), [
            'name' => $validated['name'],
            'life_area_id' => $validated['life_area_id'] ?? null,
            'category' => RoutineItemCategory::from($validated['category']),
            'tracking_type' => TrackingType::from($validated['tracking_type']),
            'default_load_unit' => $validated['default_load_unit'] ?? null,
            'default_amount_unit' => $validated['default_amount_unit'] ?? null,
            'note' => $validated['note'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'routine_item' => RoutineItemResource::make($routineItem)->resolve(),
        ]);
    }

    public function update(
        UpdateRoutineItemRequest $request,
        RoutineItem $item,
        UpdateRoutineItemService $service,
    ): JsonResponse {
        Gate::authorize('update', $item);

        $validated = $request->validated();
        $attributes = $validated;

        if (isset($validated['category'])) {
            $attributes['category'] = RoutineItemCategory::from($validated['category']);
        }

        if (isset($validated['tracking_type'])) {
            $attributes['tracking_type'] = TrackingType::from($validated['tracking_type']);
        }

        $updated = $service->handle($item, $attributes);

        return response()->json([
            'routine_item' => RoutineItemResource::make($updated)->resolve(),
        ]);
    }

    public function destroy(RoutineItem $item, DeleteRoutineItemService $service): JsonResponse
    {
        Gate::authorize('delete', $item);

        $service->handle($item);

        return response()->json(['deleted' => true]);
    }
}
