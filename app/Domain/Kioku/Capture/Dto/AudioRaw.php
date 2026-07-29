<?php

namespace App\Domain\Kioku\Capture\Dto;

use App\Domain\Kioku\Capture\CaptureChannel;
use App\Domain\Kioku\Capture\RawKind;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;

readonly class AudioRaw extends CapturedRaw
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        int $userId,
        ?string $clientCaptureId,
        CaptureChannel $captureChannel,
        CarbonInterface $capturedAt,
        public UploadedFile $uploadedFile,
        public string $serverDetectedMime,
        public ?string $originalFilename,
        public ?int $declaredDurationMs,
        bool $sensitive = false,
        array $metadata = [],
    ) {
        parent::__construct(
            userId: $userId,
            clientCaptureId: $clientCaptureId,
            rawKind: RawKind::Audio,
            captureChannel: $captureChannel,
            capturedAt: $capturedAt,
            sensitive: $sensitive,
            metadata: $metadata,
        );
    }

    public function legacySourceType(): string
    {
        // Keep voice for existing UI / jobs that still branch on source_type.
        return 'voice';
    }
}
