<?php

namespace App\Domain\Kioku\Capture\Dto;

use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Transport-agnostic capture input before Adapter conversion.
 *
 * @phpstan-type CaptureInputKind 'text'|'url'|'audio'
 */
readonly class CaptureCommand
{
    /**
     * @param  'text'|'url'|'audio'  $kind
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public User $user,
        public ?string $clientCaptureId,
        public string $kind,
        public ?string $text = null,
        public ?string $url = null,
        public ?UploadedFile $audio = null,
        public ?string $serverDetectedMime = null,
        public ?string $originalFilename = null,
        public ?int $declaredDurationMs = null,
        public ?string $capturedAt = null,
        public bool $sensitive = false,
        public array $metadata = [],
    ) {}
}
