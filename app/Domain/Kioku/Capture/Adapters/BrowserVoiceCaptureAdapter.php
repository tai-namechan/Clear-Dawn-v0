<?php

namespace App\Domain\Kioku\Capture\Adapters;

use App\Domain\Kioku\Capture\CaptureAdapter;
use App\Domain\Kioku\Capture\CaptureChannel;
use App\Domain\Kioku\Capture\Dto\AudioRaw;
use App\Domain\Kioku\Capture\Dto\CaptureCommand;
use App\Domain\Kioku\Capture\Dto\CapturedRaw;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class BrowserVoiceCaptureAdapter implements CaptureAdapter
{
    public function supports(CaptureCommand $command): bool
    {
        return $command->kind === 'audio'
            && (($command->metadata['capture_channel'] ?? CaptureChannel::BrowserVoice->value) === CaptureChannel::BrowserVoice->value);
    }

    public function toCapturedRaw(CaptureCommand $command): CapturedRaw
    {
        if ($command->audio === null) {
            throw new InvalidArgumentException('Browser voice capture requires an audio file.');
        }

        $capturedAt = $command->capturedAt !== null
            ? CarbonImmutable::parse($command->capturedAt)
            : CarbonImmutable::now();

        $mime = $command->serverDetectedMime
            ?? $command->audio->getMimeType()
            ?? 'application/octet-stream';

        return new AudioRaw(
            userId: (int) $command->user->id,
            clientCaptureId: $command->clientCaptureId,
            captureChannel: CaptureChannel::BrowserVoice,
            capturedAt: $capturedAt,
            uploadedFile: $command->audio,
            serverDetectedMime: $mime,
            originalFilename: $command->originalFilename ?? $command->audio->getClientOriginalName(),
            declaredDurationMs: $command->declaredDurationMs,
            sensitive: $command->sensitive,
            metadata: $command->metadata,
        );
    }
}
