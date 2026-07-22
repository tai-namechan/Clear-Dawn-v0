<?php

namespace App\Http\Controllers;

use App\Domain\Shared\AI\QuotaExceededException;
use App\Http\Requests\FoodLookups\StoreFoodMenuEstimateRequest;
use App\Http\Requests\FoodLookups\StoreFoodPhotoRequest;
use App\Http\Resources\FoodItemResource;
use App\Services\StartFoodMenuEstimateService;
use App\Services\StartFoodPhotoEstimateService;
use Illuminate\Http\JsonResponse;

class RestaurantLookupController extends Controller
{
    public function storePhotoEstimate(
        StoreFoodPhotoRequest $request,
        StartFoodPhotoEstimateService $service,
    ): JsonResponse {
        try {
            $lookup = $service->start($request->user(), $request->validated('image'));
        } catch (QuotaExceededException) {
            return $this->quotaExceededResponse();
        }

        return response()->json([
            'status' => 'ai_pending',
            'lookup_id' => $lookup->id,
        ], 202);
    }

    public function storeMenuEstimate(
        StoreFoodMenuEstimateRequest $request,
        StartFoodMenuEstimateService $service,
    ): JsonResponse {
        try {
            $result = $service->start(
                $request->user(),
                $request->validated('store_name'),
                $request->validated('menu_name'),
            );
        } catch (QuotaExceededException) {
            return $this->quotaExceededResponse();
        }

        if ($result['status'] === 'hit') {
            return response()->json([
                'status' => 'hit',
                'food' => new FoodItemResource($result['food']),
            ]);
        }

        return response()->json([
            'status' => 'ai_pending',
            'lookup_id' => $result['lookup']->id,
        ], 202);
    }

    private function quotaExceededResponse(): JsonResponse
    {
        return response()->json([
            'message' => '今月のAI利用枠を使い切りました。来月また利用できます。',
        ], 422);
    }
}
