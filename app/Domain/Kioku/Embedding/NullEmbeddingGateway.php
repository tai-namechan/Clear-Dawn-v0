<?php

namespace App\Domain\Kioku\Embedding;

use RuntimeException;

final class NullEmbeddingGateway implements EmbeddingGateway
{
    public function embed(EmbeddingRequest $request): EmbeddingResult
    {
        throw new RuntimeException('Embedding provider is not configured (KIOKU_EMBEDDING_PROVIDER=none).');
    }
}
