<?php

namespace App\Domain\Kioku\Capture\Dto;

use App\Domain\Kioku\Capture\CaptureChannel;
use App\Domain\Kioku\Capture\RawKind;
use Carbon\CarbonInterface;

/**
 * Typed ingress payload after CaptureAdapter conversion.
 * Media-specific subclasses carry the canonical raw bytes/text.
 */
abstract readonly class CapturedRaw
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $userId,
        public ?string $clientCaptureId,
        public RawKind $rawKind,
        public CaptureChannel $captureChannel,
        public CarbonInterface $capturedAt,
        public bool $sensitive = false,
        public array $metadata = [],
    ) {}

    /**
     * Legacy source_type kept for display / aggregation compatibility.
     */
    abstract public function legacySourceType(): string;
}
