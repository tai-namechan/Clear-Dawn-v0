<?php

namespace App\Domain\Kioku\Embedding\Store;

use App\Domain\Kioku\Embedding\VectorStore;
use App\Domain\Kioku\Models\MemoryEmbedding;

/**
 * MVP vector store: JSON/longText vectors + PHP cosine similarity.
 * Hard-capped per user via config; replaceable via VectorStore interface.
 */
final class MysqlJsonVectorStore implements VectorStore
{
    public function upsert(MemoryEmbedding $embedding): void
    {
        // Eloquent save is the persistence path; this method exists for the interface.
        $embedding->save();
    }

    public function deleteForMemory(string $memoryId, ?int $userId = null): void
    {
        $query = MemoryEmbedding::query()->withoutUserScope()->where('memory_id', $memoryId);
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        $query->delete();
    }

    public function nearest(int $userId, array $vector, int $topK = 40): array
    {
        $topK = max(1, min($topK, (int) config('kioku.semantic_search.top_k', 40)));
        $cap = (int) config('kioku.embedding.max_memories_per_user', 1000);

        $rows = MemoryEmbedding::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('status', 'ready')
            ->orderByDesc('embedded_at')
            ->limit($cap)
            ->get(['memory_id', 'vector', 'dimensions']);

        $scored = [];
        foreach ($rows as $row) {
            $candidate = $row->vectorArray();
            if ($candidate === [] || count($candidate) !== count($vector)) {
                continue;
            }
            $score = $this->cosine($vector, $candidate);
            $scored[] = ['memory_id' => (string) $row->memory_id, 'score' => $score];
        }

        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $topK);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        $n = count($a);
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
