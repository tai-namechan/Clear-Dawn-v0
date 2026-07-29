<?php

namespace App\Domain\Kioku\Capture\Dto;

/**
 * Derived text representation ready for Enrich. Regenerable.
 */
readonly class NormalizedContent
{
    public function __construct(
        public string $text,
        public ?string $source = null,
    ) {}
}
