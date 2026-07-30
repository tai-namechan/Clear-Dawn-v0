<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Audio\AudioDurationProbe;
use App\Domain\Kioku\Capture\Adapters\CaptureAdapterRegistry;
use App\Domain\Kioku\Capture\CanonicalRawStore;
use App\Domain\Kioku\Capture\CaptureChannel;
use App\Domain\Kioku\Capture\Dto\CaptureCommand;
use App\Domain\Kioku\Capture\MemoryProcessingPipeline;
use App\Domain\Kioku\Models\Memory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

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
        private AudioDurationProbe $durationProbe,
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
     * duration_ms is measured on the server when possible. Client declarations
     * are never used for file-import / iOS paths, and never defaulted to 1.
     *
     * @return array{memory: Memory, created: bool}
     */
    public function captureVoice(
        User $user,
        UploadedFile $audio,
        string $clientCaptureId,
        ?int $durationMs = null,
        ?string $capturedAt = null,
        bool $sensitive = false,
        CaptureChannel $channel = CaptureChannel::BrowserVoice,
        ?string $serverDetectedMime = null,
    ): array {
        $resolvedDuration = $this->resolveDurationMs($audio, $channel, $durationMs);

        $command = new CaptureCommand(
            user: $user,
            clientCaptureId: $clientCaptureId,
            kind: 'audio',
            audio: $audio,
            serverDetectedMime: $serverDetectedMime ?? $audio->getMimeType(),
            originalFilename: $audio->getClientOriginalName(),
            declaredDurationMs: $resolvedDuration,
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

    public static function maxDurationMsForChannel(CaptureChannel|string|null $channel): int
    {
        $value = $channel instanceof CaptureChannel ? $channel->value : $channel;

        return match ($value) {
            CaptureChannel::AudioFileImport->value,
            CaptureChannel::IosShortcut->value => (int) config('kioku.audio_import.max_duration_ms', 7_200_000),
            default => (int) config('kioku.audio.max_duration_ms', 180_000),
        };
    }

    private function resolveDurationMs(
        UploadedFile $audio,
        CaptureChannel $channel,
        ?int $declaredDurationMs,
    ): ?int {
        $maxMs = self::maxDurationMsForChannel($channel);
        $probed = $this->durationProbe->fromUploadedFile($audio);

        if ($probed !== null && $probed > $maxMs) {
            throw ValidationException::withMessages([
                'audio' => ['音声の長さが上限を超えています。'],
                'duration_ms' => ['音声の長さが上限を超えています。'],
            ]);
        }

        if ($probed !== null) {
            return $probed;
        }

        // File import / iOS: never trust client placeholders (e.g. duration_ms=1).
        if (in_array($channel, [CaptureChannel::AudioFileImport, CaptureChannel::IosShortcut], true)) {
            return null;
        }

        if ($declaredDurationMs === null || $declaredDurationMs < 1) {
            return null;
        }

        if ($declaredDurationMs > $maxMs) {
            throw ValidationException::withMessages([
                'duration_ms' => ['送信された録音時間が上限を超えています。'],
            ]);
        }

        return $declaredDurationMs;
    }
}
