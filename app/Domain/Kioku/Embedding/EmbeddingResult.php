<?php

namespace App\Domain\Kioku\Embedding;

readonly class EmbeddingResult
{
    /**
     * @param  list<float>  $vector
     */
    public function __construct(
        public array $vector,
        public string $model,
        public int $dimensions,
        public int $inputTokens,
        public ?string $requestId,
        public string $actualUsd,
        public string $provider,
    ) {}
}
