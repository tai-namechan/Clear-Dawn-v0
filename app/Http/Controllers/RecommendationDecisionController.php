<?php

namespace App\Http\Controllers;

use App\Http\Requests\Recommendations\DecideRecommendationRequest;
use App\Models\Recommendation;
use App\Services\DecideRecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RecommendationDecisionController extends Controller
{
    public function store(
        DecideRecommendationRequest $request,
        Recommendation $recommendation,
        DecideRecommendationService $service,
    ): JsonResponse {
        Gate::authorize('decide', $recommendation);

        $decision = $service->handle($request->user(), $recommendation, $request->validated());

        return response()->json([
            'decision' => [
                'id' => $decision->id,
                'recommendation_id' => $decision->recommendation_id,
                'action_key' => $decision->action_key,
                'reason' => $decision->reason,
                'result_snapshot' => $decision->result_snapshot,
            ],
        ], 201);
    }
}
