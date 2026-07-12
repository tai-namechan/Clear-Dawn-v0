<?php

namespace App\Http\Controllers\Kioku;

use App\Domain\Kioku\Models\KiokuCaptureEvent;
use App\Domain\Kioku\Services\CaptureMemoryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kioku\StoreCaptureEventRequest;
use App\Http\Requests\Kioku\StoreManualCaptureRequest;
use App\Http\Requests\Kioku\StoreVoiceCaptureRequest;
use App\Http\Resources\Kioku\MemoryResource;
use Illuminate\Http\JsonResponse;

/**
 * JSON endpoints for the client-side capture queue. Unlike the legacy
 * Inertia store, responses are plain JSON so the IndexedDB queue can sync
 * in the background and reconcile via client_capture_id.
 */
class CaptureController extends Controller
{
    public function manual(
        StoreManualCaptureRequest $request,
        CaptureMemoryService $service,
    ): JsonResponse {
        $result = $service->captureText(
            user: $request->user(),
            rawContent: (string) $request->validated('raw_content'),
            clientCaptureId: (string) $request->validated('client_capture_id'),
            capturedAt: $request->validated('captured_at'),
            sensitive: (bool) ($request->validated('sensitive') ?? false),
        );

        return response()->json([
            'memory' => (new MemoryResource($result['memory']))->resolve(),
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }

    public function voice(
        StoreVoiceCaptureRequest $request,
        CaptureMemoryService $service,
    ): JsonResponse {
        $result = $service->captureVoice(
            user: $request->user(),
            audio: $request->file('audio'),
            clientCaptureId: (string) $request->validated('client_capture_id'),
            durationMs: (int) $request->validated('duration_ms'),
            capturedAt: $request->validated('captured_at'),
            sensitive: (bool) ($request->validated('sensitive') ?? false),
        );

        return response()->json([
            'memory' => (new MemoryResource($result['memory']))->resolve(),
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }

    public function event(StoreCaptureEventRequest $request): JsonResponse
    {
        KiokuCaptureEvent::query()->create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return response()->json(['recorded' => true], 201);
    }
}
