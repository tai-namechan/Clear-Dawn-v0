<?php

namespace App\Domain\Kioku\Capture\Adapters;

use App\Domain\Kioku\Capture\CaptureAdapter;
use App\Domain\Kioku\Capture\CaptureChannel;
use App\Domain\Kioku\Capture\Dto\AudioRaw;
use App\Domain\Kioku\Capture\Dto\CaptureCommand;
use App\Domain\Kioku\Capture\Dto\CapturedRaw;
use App\Domain\Kioku\Capture\Dto\TextRaw;
use App\Domain\Kioku\Capture\Dto\UrlRaw;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * iOS Shortcut ingress. Channel is always ios_shortcut; raw kind varies.
 */
final class IosShortcutCaptureAdapter implements CaptureAdapter
{
    public function supports(CaptureCommand $command): bool
    {
        return ($command->metadata['capture_channel'] ?? null) === CaptureChannel::IosShortcut->value;
    }

    public function toCapturedRaw(CaptureCommand $command): CapturedRaw
    {
        $capturedAt = $command->capturedAt !== null
            ? CarbonImmutable::parse($command->capturedAt)
            : CarbonImmutable::now();

        return match ($command->kind) {
            'text' => new TextRaw(
                userId: (int) $command->user->id,
                clientCaptureId: $command->clientCaptureId,
                captureChannel: CaptureChannel::IosShortcut,
                capturedAt: $capturedAt,
                text: trim((string) $command->text),
                sensitive: $command->sensitive,
                metadata: $command->metadata,
            ),
            'url' => new UrlRaw(
                userId: (int) $command->user->id,
                clientCaptureId: $command->clientCaptureId,
                captureChannel: CaptureChannel::IosShortcut,
                capturedAt: $capturedAt,
                url: trim((string) $command->url),
                sensitive: $command->sensitive,
                metadata: $command->metadata,
            ),
            'audio' => $this->audioRaw($command, $capturedAt),
        };
    }

    private function audioRaw(CaptureCommand $command, CarbonImmutable $capturedAt): AudioRaw
    {
        $audio = $command->audio;
        if ($audio === null) {
            throw new InvalidArgumentException('iOS audio capture requires a file.');
        }

        return new AudioRaw(
            userId: (int) $command->user->id,
            clientCaptureId: $command->clientCaptureId,
            captureChannel: CaptureChannel::IosShortcut,
            capturedAt: $capturedAt,
            uploadedFile: $audio,
            serverDetectedMime: $command->serverDetectedMime
                ?? $audio->getMimeType()
                ?? 'application/octet-stream',
            originalFilename: $command->originalFilename ?? $audio->getClientOriginalName(),
            declaredDurationMs: $command->declaredDurationMs,
            sensitive: $command->sensitive,
            metadata: $command->metadata,
        );
    }
}
