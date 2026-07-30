<?php

namespace App\Domain\Kioku\Embedding;

use App\Domain\Kioku\Models\MemoryEmbedding;

interface VectorStore
{
    public function upsert(MemoryEmbedding $embedding): void;

    public function deleteForMemory(string $memoryId, ?int $userId = null): void;

    /**
     * @param  list<float>  $vector
     * @return list<array{memory_id: string, score: float}>
     */
    public function nearest(int $userId, array $vector, int $topK = 40): array;
}
