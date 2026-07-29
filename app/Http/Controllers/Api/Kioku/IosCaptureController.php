<?php

namespace App\Http\Controllers\Api\Kioku;

use App\Domain\Kioku\Capture\CaptureChannel;
use App\Domain\Kioku\Capture\Dto\CaptureCommand;
use App\Domain\Kioku\Services\CaptureMemoryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kioku\StoreAudioFileImportRequest;
use App\Http\Resources\Kioku\MemoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Scoped-token capture API for iOS Shortcuts. Reuses CaptureMemoryService /
 * Canonical pipeline — does not duplicate browser capture logic.
 */
class IosCaptureController extends Controller
{
    public function store(Request $request, CaptureMemoryService $service): JsonResponse
    {
        $idempotency = (string) ($request->header('Idempotency-Key')
            ?? $request->input('client_capture_id', ''));

        if ($idempotency === '') {
            return response()->json(['message' => 'Idempotency-Key is required.'], 422);
        }

        $kind = (string) $request->input('kind', $request->hasFile('audio') ? 'audio' : 'text');

        if ($kind === 'audio') {
            return $this->storeAudio($request, $service, $idempotency);
        }

        $validator = Validator::make($request->all(), [
            'kind' => ['nullable', Rule::in(['text', 'url'])],
            'text' => ['required_without:url', 'nullable', 'string', 'max:20000'],
            'url' => ['required_without:text', 'nullable', 'url', 'max:2000'],
            'sensitive' => ['nullable', 'boolean'],
            'captured_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $resolvedKind = isset($data['url']) && trim((string) $data['url']) !== '' ? 'url' : 'text';

        $result = $service->capture(new CaptureCommand(
            user: $request->user(),
            clientCaptureId: $idempotency,
            kind: $resolvedKind,
            text: $resolvedKind === 'text' ? (string) ($data['text'] ?? '') : null,
            url: $resolvedKind === 'url' ? (string) ($data['url'] ?? '') : null,
            capturedAt: $data['captured_at'] ?? null,
            sensitive: (bool) ($data['sensitive'] ?? false),
            metadata: [
                'capture_channel' => CaptureChannel::IosShortcut->value,
            ],
        ));

        return response()->json([
            'memory' => (new MemoryResource($result['memory']))->resolve(),
            'created' => $result['created'],
            'message' => '原情報を保存しました',
        ], $result['created'] ? 201 : 200);
    }

    private function storeAudio(Request $request, CaptureMemoryService $service, string $idempotency): JsonResponse
    {
        $form = StoreAudioFileImportRequest::createFrom($request);
        $form->setContainer(app())->setRedirector(app('redirect'));
        $form->setUserResolver(fn () => $request->user());

        // Force flag on for scoped API when ios shortcut is enabled.
        config(['kioku.audio_import.enabled' => true]);

        $validator = Validator::make($request->all(), [
            'audio' => ['required', 'file', 'max:'.((int) ceil(((int) config('kioku.audio_import.max_bytes')) / 1024)), 'mimetypes:'.implode(',', StoreAudioFileImportRequest::ALLOWED_MIME_TYPES)],
            'duration_ms' => ['nullable', 'integer', 'min:1'],
            'sensitive' => ['nullable', 'boolean'],
            'captured_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $audio = $request->file('audio');
        $result = $service->captureVoice(
            user: $request->user(),
            audio: $audio,
            clientCaptureId: $idempotency,
            durationMs: (int) ($request->input('duration_ms') ?? 1),
            capturedAt: $request->input('captured_at'),
            sensitive: (bool) $request->boolean('sensitive'),
            channel: CaptureChannel::IosShortcut,
            serverDetectedMime: $audio->getMimeType() ?? 'application/octet-stream',
        );

        return response()->json([
            'memory' => (new MemoryResource($result['memory']))->resolve(),
            'created' => $result['created'],
            'message' => '原情報を保存しました',
        ], $result['created'] ? 201 : 200);
    }
}
