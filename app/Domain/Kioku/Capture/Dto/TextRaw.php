<?php

namespace App\Domain\Kioku\Capture\Dto;

use App\Domain\Kioku\Capture\CaptureChannel;
use App\Domain\Kioku\Capture\RawKind;
use Carbon\CarbonInterface;

readonly class TextRaw extends CapturedRaw
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        int $userId,
        ?string $clientCaptureId,
        CaptureChannel $captureChannel,
        CarbonInterface $capturedAt,
        public string $text,
        bool $sensitive = false,
        array $metadata = [],
    ) {
        parent::__construct(
            userId: $userId,
            clientCaptureId: $clientCaptureId,
            rawKind: RawKind::Text,
            captureChannel: $captureChannel,
            capturedAt: $capturedAt,
            sensitive: $sensitive,
            metadata: $metadata,
        );
    }

    public function legacySourceType(): string
    {
        return 'manual';
    }
}
