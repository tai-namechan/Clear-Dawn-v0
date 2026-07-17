<?php

namespace App\Http\Controllers;

use App\Http\Requests\SymptomObservations\StoreSymptomObservationRequest;
use App\Services\EvaluateRulesForDayService;
use App\Services\RecordSymptomObservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class SymptomObservationController extends Controller
{
    public function store(
        StoreSymptomObservationRequest $request,
        RecordSymptomObservationService $service,
        EvaluateRulesForDayService $evaluateRules,
    ): JsonResponse {
        $date = Carbon::parse($request->validated('observed_on') ?? now()->toDateString());
        $user = $request->user();

        $symptom = $service->handle($user, $date, $request->validated());
        $evaluateRules->handle($user, $date);

        return response()->json([
            'symptom' => [
                'id' => $symptom->id,
                'observed_on' => $symptom->observed_on->toDateString(),
                'body_region' => $symptom->body_region,
                'symptom_kind' => $symptom->symptom_kind,
                'severity' => $symptom->severity,
                'is_new' => $symptom->is_new,
                'note' => $symptom->note,
            ],
        ], 201);
    }
}
