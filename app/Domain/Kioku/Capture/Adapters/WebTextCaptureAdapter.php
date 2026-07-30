<?php

namespace App\Domain\Kioku\Capture\Adapters;

use App\Domain\Kioku\Capture\CaptureAdapter;
use App\Domain\Kioku\Capture\CaptureChannel;
use App\Domain\Kioku\Capture\Dto\CaptureCommand;
use App\Domain\Kioku\Capture\Dto\CapturedRaw;
use App\Domain\Kioku\Capture\Dto\TextRaw;
use App\Domain\Kioku\Capture\Dto\UrlRaw;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Web quick-capture text box. Auto-promotes plain URL strings to UrlRaw.
 */
final class WebTextCaptureAdapter implements CaptureAdapter
{
    public function supports(CaptureCommand $command): bool
    {
        return $command->kind === 'text' || $command->kind === 'url';
    }

    public function toCapturedRaw(CaptureCommand $command): CapturedRaw
    {
        if (! $this->supports($command)) {
            throw new InvalidArgumentException('WebTextCaptureAdapter does not support this command.');
        }

        $capturedAt = $command->capturedAt !== null
            ? CarbonImmutable::parse($command->capturedAt)
            : CarbonImmutable::now();

        $channel = isset($command->metadata['capture_channel'])
            && is_string($command->metadata['capture_channel'])
            ? CaptureChannel::from($command->metadata['capture_channel'])
            : CaptureChannel::WebText;

        if ($command->kind === 'url') {
            $url = trim((string) $command->url);
            if ($url === '') {
                throw new InvalidArgumentException('URL capture requires a non-empty url.');
            }

            return new UrlRaw(
                userId: (int) $command->user->id,
                clientCaptureId: $command->clientCaptureId,
                captureChannel: $channel === CaptureChannel::WebText ? CaptureChannel::WebUrl : $channel,
                capturedAt: $capturedAt,
                url: $url,
                sensitive: $command->sensitive,
                metadata: $command->metadata,
            );
        }

        $text = trim((string) $command->text);
        if ($text === '') {
            throw new InvalidArgumentException('Text capture requires non-empty text.');
        }

        if (filter_var($text, FILTER_VALIDATE_URL)) {
            return new UrlRaw(
                userId: (int) $command->user->id,
                clientCaptureId: $command->clientCaptureId,
                captureChannel: CaptureChannel::WebUrl,
                capturedAt: $capturedAt,
                url: $text,
                sensitive: $command->sensitive,
                metadata: $command->metadata,
            );
        }

        return new TextRaw(
            userId: (int) $command->user->id,
            clientCaptureId: $command->clientCaptureId,
            captureChannel: $channel,
            capturedAt: $capturedAt,
            text: $text,
            sensitive: $command->sensitive,
            metadata: $command->metadata,
        );
    }
}
