<?php

namespace App\Domain\Kioku\Embedding;

/**
 * Deterministic fake for tests. Never performs HTTP.
 */
final class FakeEmbeddingGateway implements EmbeddingGateway
{
    public int $calls = 0;

    /** @var list<string> */
    public array $texts = [];

    public function embed(EmbeddingRequest $request): EmbeddingResult
    {
        $this->calls++;
        $this->texts[] = $request->text;

        $dims = $request->dimensions ?? (int) config('kioku.embedding.dimensions', 8);
        $dims = max(2, min($dims, 32));
        $seed = crc32($request->text);
        $vector = [];
        for ($i = 0; $i < $dims; $i++) {
            $vector[] = (($seed >> ($i % 16)) & 0xFF) / 255.0;
        }

        return new EmbeddingResult(
            vector: $vector,
            model: $request->model,
            dimensions: $dims,
            inputTokens: max(1, (int) ceil(mb_strlen($request->text) / 4)),
            requestId: 'fake-'.substr(hash('sha256', $request->text), 0, 12),
            actualUsd: '0.000001',
            provider: 'fake',
        );
    }
}
