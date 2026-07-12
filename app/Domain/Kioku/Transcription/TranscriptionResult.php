<?php

namespace App\Domain\Kioku\Transcription;

final readonly class TranscriptionResult
{
    public function __construct(
        public string $text,
        public string $provider,
        public ?string $model = null,
    ) {}
}
