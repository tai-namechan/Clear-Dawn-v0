<?php

namespace App\Domain\Kioku\Embedding;

interface EmbeddingGateway
{
    public function embed(EmbeddingRequest $request): EmbeddingResult;
}
