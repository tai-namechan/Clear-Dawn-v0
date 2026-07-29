<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Capture\Adapters\CaptureAdapterRegistry;
use App\Domain\Kioku\Capture\CanonicalRawStore;
use App\Domain\Kioku\Capture\CaptureChannel;
use App\Domain\Kioku\Capture\Dto\CaptureCommand;
use App\Domain\Kioku\Capture\MemoryProcessingPipeline;
use App\Domain\Kioku\Models\Memory;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Single write path for quick captures, shared by the legacy Inertia store
 * and the JSON capture endpoints. Idempotent on (user_id, client_capture_id)
 * so the client-side queue can resend safely.
 *
 * Internally routes through CaptureAdapter → CanonicalRawStore → Pipeline
 * without changing the public endpoint contract.
 */
final class CaptureMemoryService
{
    public function __construct(
        private CaptureAdapterRegistry $adapters,
        private CanonicalRawStore $store,
        private MemoryProcessingPipeline $pipeline,
    ) {}

    /**
     * @return array{memory: Memory, created: bool}
     */
    public function captureText(
        User $user,
        string $rawContent,
        string $sourceType = 'manual',
        ?string $clientCaptureId = null,
        ?string $capturedAt = null,
        bool $sensitive = false,
    ): array {
        $kind = $sourceType === 'url' ? 'url' : 'text';
        $command = new CaptureCommand(
            user: $user,
            clientCaptureId: $clientCaptureId,
            kind: $kind,
            text: $kind === 'text' ? $rawContent : null,
            url: $kind === 'url' ? $rawContent : null,
            capturedAt: $capturedAt,
            sensitive: $sensitive,
            metadata: [
                'capture_channel' => $kind === 'url'
                    ? CaptureChannel::WebUrl->value
                    : CaptureChannel::WebText->value,
                'legacy_source_type' => $sourceType,
            ],
        );

        return $this->capture($command);
    }

    /**
     * Voice capture: the audio original is the canonical raw.
     *
     * @return array{memory: Memory, created: bool}
     */
    public function captureVoice(
        User $user,
        UploadedFile $audio,
        string $clientCaptureId,
        int $durationMs,
        ?string $capturedAt = null,
        bool $sensitive = false,
        CaptureChannel $channel = CaptureChannel::BrowserVoice,
        ?string $serverDetectedMime = null,
    ): array {
        $command = new CaptureCommand(
            user: $user,
            clientCaptureId: $clientCaptureId,
            kind: 'audio',
            audio: $audio,
            serverDetectedMime: $serverDetectedMime ?? $audio->getMimeType(),
            originalFilename: $audio->getClientOriginalName(),
            declaredDurationMs: $durationMs,
            capturedAt: $capturedAt,
            sensitive: $sensitive,
            metadata: [
                'capture_channel' => $channel->value,
            ],
        );

        return $this->capture($command);
    }

    /**
     * Generic entry used by new adapters (file import, iOS Shortcut, etc.).
     *
     * @return array{memory: Memory, created: bool}
     */
    public function capture(CaptureCommand $command): array
    {
        $raw = $this->adapters->toCapturedRaw($command);
        $result = $this->store->persist($raw);

        if ($result['created']) {
            $this->pipeline->dispatchAfterCapture($result['memory']);
        }

        return $result;
    }
}
