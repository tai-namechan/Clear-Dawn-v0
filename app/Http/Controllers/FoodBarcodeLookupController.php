<?php

namespace App\Http\Controllers;

use App\Enums\FoodLookupStatus;
use App\Http\Requests\FoodLookups\ConfirmFoodLookupRequest;
use App\Http\Requests\FoodLookups\StoreFoodBarcodeLookupRequest;
use App\Http\Resources\FoodItemResource;
use App\Models\FoodLookupRequest;
use App\Services\ConfirmFoodLookupService;
use App\Services\StartFoodBarcodeLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FoodBarcodeLookupController extends Controller
{
    public function store(
        StoreFoodBarcodeLookupRequest $request,
        StartFoodBarcodeLookupService $service,
    ): JsonResponse {
        $result = $service->handle($request->user(), $request->validated('barcode'));

        if ($result['status'] === 'hit') {
            return response()->json([
                'status' => 'hit',
                'food' => FoodItemResource::make($result['food'])->resolve(),
            ]);
        }

        return response()->json([
            'status' => 'pending',
            'lookup_id' => $result['lookup']->id,
        ], 202);
    }

    public function show(Request $request, string $lookupId): JsonResponse
    {
        $lookup = FoodLookupRequest::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($lookupId)
            ->firstOrFail();

        $data = [
            'status' => $lookup->status->value,
        ];

        if ($lookup->status === FoodLookupStatus::Found) {
            $data['result'] = $lookup->result;
            $data['source'] = $lookup->source;
        }

        if ($lookup->status === FoodLookupStatus::Failed) {
            $data['error_code'] = $lookup->error_code;
        }

        return response()->json($data);
    }

    public function confirm(
        ConfirmFoodLookupRequest $request,
        string $lookupId,
        ConfirmFoodLookupService $service,
    ): JsonResponse {
        $lookup = FoodLookupRequest::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($lookupId)
            ->where('status', FoodLookupStatus::Found)
            ->firstOrFail();

        /** @var array{name: string, serving_label: string, kcal: float|int|string, protein_g: float|int|string, fat_g: float|int|string, carb_g: float|int|string} $validated */
        $validated = $request->validated();

        $food = $service->handle($request->user(), $lookup, $validated);

        return response()->json([
            'food' => FoodItemResource::make($food)->resolve(),
        ], 201);
    }
}
