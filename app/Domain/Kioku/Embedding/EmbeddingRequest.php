<?php

namespace App\Domain\Kioku\Embedding;

readonly class EmbeddingRequest
{
    public function __construct(
        public int $userId,
        public string $memoryId,
        public string $text,
        public string $model,
        public ?int $dimensions = null,
    ) {}
}
