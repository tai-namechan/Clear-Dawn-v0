<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Embedding\EmbeddingGateway;
use App\Domain\Kioku\Embedding\EmbeddingRequest;
use App\Domain\Kioku\Embedding\VectorStore;
use App\Domain\Kioku\Models\KiokuRecallFeedback;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tag + fulltext + vector + explicit-link candidate pools fused with RRF.
 * Falls back to tag+fulltext when semantic search is disabled or embedding fails.
 */
final class HybridSearchService
{
    private const RRF_K = 60;

    public function __construct(
        private KiokuSearchService $legacySearch,
        private EmbeddingGateway $embeddings,
        private VectorStore $vectors,
    ) {}

    /**
     * @param  array{types?: list<string>, tags?: list<string>, tag_mode?: string, semantic?: bool}  $filters
     * @return array{mode: string, session_id: string, query_hash: string, results: list<array<string, mixed>>}
     */
    public function recall(int $userId, string $query, array $filters = [], int $limit = 4): array
    {
        $sessionId = (string) Str::uuid();
        $query = trim($query);
        $limit = max(1, min($limit, (int) config('kioku.semantic_search.recall_limit', 4)));
        $wantSemantic = ($filters['semantic'] ?? true)
            && (bool) config('kioku.semantic_search.enabled', false)
            && (bool) config('kioku.embedding.enabled', false)
            && $query !== '';

        $baseFilters = [
            'types' => $filters['types'] ?? [],
            'tags' => $filters['tags'] ?? [],
            'tag_mode' => $filters['tag_mode'] ?? 'and',
        ];

        $fulltext = $this->rankedIds(
            $this->legacySearch->search($userId, $query !== '' ? $query : null, $baseFilters, 40)
                ->filter(fn (Memory $m) => $this->isSearchable($m))
                ->values()
        );

        $tagOnly = [];
        if (! empty($baseFilters['tags'])) {
            $tagOnly = $this->rankedIds(
                $this->legacySearch->search($userId, null, $baseFilters, 40)
                    ->filter(fn (Memory $m) => $this->isSearchable($m))
                    ->values()
            );
        }

        $vector = [];
        $mode = 'tag_fulltext';
        if ($wantSemantic) {
            try {
                $vector = $this->vectorRanks($userId, $query);
                $mode = 'hybrid';
            } catch (Throwable $e) {
                Log::warning('HybridSearch vector pool failed; falling back', [
                    'user_id' => $userId,
                ]);
            }
        }

        $links = $this->linkRanks($userId, array_keys($fulltext + $tagOnly + $vector));

        $scores = [];
        $reasons = [];
        $this->accumulate($scores, $reasons, $tagOnly, 1.4, 'tag');
        $this->accumulate($scores, $reasons, $vector, 1.2, 'vector');
        $this->accumulate($scores, $reasons, $fulltext, 1.0, 'fulltext');
        $this->accumulate($scores, $reasons, $links, 1.3, 'link');

        $this->applyFeedbackBoost($userId, $scores);

        arsort($scores);
        $topIds = array_slice(array_keys($scores), 0, $limit);

        $memories = Memory::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->whereIn('id', $topIds)
            ->get()
            ->keyBy('id');

        $results = [];
        $rank = 1;
        foreach ($topIds as $id) {
            $memory = $memories->get($id);
            if ($memory === null || ! $this->isSearchable($memory)) {
                continue;
            }
            $results[] = [
                'memory' => $memory,
                'rank' => $rank,
                'score' => round((float) $scores[$id], 6),
                'reason' => $reasons[$id] ?? '関連する記憶',
                'tag_rank' => $tagOnly[$id] ?? null,
                'fulltext_rank' => $fulltext[$id] ?? null,
                'vector_rank' => $vector[$id] ?? null,
            ];
            $rank++;
        }

        return [
            'mode' => $mode,
            'session_id' => $sessionId,
            'query_hash' => hash('sha256', mb_strtolower($query)),
            'results' => $results,
        ];
    }

    /**
     * Backward-compatible library search: uses legacy path unless semantic=1.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Memory>
     */
    public function search(int $userId, ?string $query, array $filters = [], int $limit = 50): Collection
    {
        $semantic = filter_var($filters['semantic'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $semantic || $query === null || trim($query) === '') {
            return $this->legacySearch->search($userId, $query, $filters, $limit);
        }

        $recall = $this->recall($userId, $query, $filters, min($limit, 40));

        return collect($recall['results'])->map(fn (array $row) => $row['memory'])->values();
    }

    private function isSearchable(Memory $memory): bool
    {
        return $memory->status === 'ready'
            && ! $memory->sensitive
            && $memory->source_type !== 'kioku_letter';
    }

    /**
     * @param  Collection<int, Memory>  $memories
     * @return array<string, int> memory_id => rank (1-based)
     */
    private function rankedIds(Collection $memories): array
    {
        $ranks = [];
        $i = 1;
        foreach ($memories as $memory) {
            $ranks[$memory->id] = $i++;
        }

        return $ranks;
    }

    /**
     * @return array<string, int>
     */
    private function vectorRanks(int $userId, string $query): array
    {
        $model = (string) config('kioku.embedding.model', 'text-embedding-3-small');
        $result = $this->embeddings->embed(new EmbeddingRequest(
            userId: $userId,
            memoryId: 'query',
            text: $query,
            model: $model,
            dimensions: (int) config('kioku.embedding.dimensions', 1536),
        ));

        $nearest = $this->vectors->nearest($userId, $result->vector, (int) config('kioku.semantic_search.top_k', 40));
        $ranks = [];
        $i = 1;
        foreach ($nearest as $hit) {
            $ranks[$hit['memory_id']] = $i++;
        }

        return $ranks;
    }

    /**
     * @param  list<string>  $seedIds
     * @return array<string, int>
     */
    private function linkRanks(int $userId, array $seedIds): array
    {
        if ($seedIds === []) {
            return [];
        }

        $linked = MemoryLink::query()
            ->whereIn('from_memory_id', $seedIds)
            ->orWhereIn('to_memory_id', $seedIds)
            ->limit(200)
            ->get(['from_memory_id', 'to_memory_id']);

        $ids = [];
        foreach ($linked as $link) {
            $ids[] = $link->from_memory_id;
            $ids[] = $link->to_memory_id;
        }
        $ids = array_values(array_unique(array_diff($ids, $seedIds)));
        if ($ids === []) {
            return [];
        }

        $owned = Memory::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->where('status', 'ready')
            ->where('sensitive', false)
            ->where('source_type', '!=', 'kioku_letter')
            ->limit(40)
            ->pluck('id');

        $ranks = [];
        $i = 1;
        foreach ($owned as $id) {
            $ranks[(string) $id] = $i++;
        }

        return $ranks;
    }

    /**
     * @param  array<string, float>  $scores
     * @param  array<string, string>  $reasons
     * @param  array<string, int>  $ranks
     */
    private function accumulate(array &$scores, array &$reasons, array $ranks, float $weight, string $source): void
    {
        foreach ($ranks as $id => $rank) {
            $scores[$id] = ($scores[$id] ?? 0.0) + ($weight / (self::RRF_K + $rank));
            $label = match ($source) {
                'tag' => 'タグが一致',
                'vector' => '意味が近い',
                'fulltext' => '文言が近い',
                'link' => '関連リンク',
                default => '関連',
            };
            if (! isset($reasons[$id])) {
                $reasons[$id] = $label;
            } elseif (! str_contains($reasons[$id], $label)) {
                $reasons[$id] .= '・'.$label;
            }
        }
    }

    /**
     * @param  array<string, float>  $scores
     */
    private function applyFeedbackBoost(int $userId, array &$scores): void
    {
        if (! config('kioku.recall_feedback.enabled', false) || $scores === []) {
            return;
        }

        $ids = array_keys($scores);
        $rows = KiokuRecallFeedback::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->whereIn('memory_id', $ids)
            ->whereIn('verdict', ['hit', 'related', 'miss'])
            ->get(['memory_id', 'verdict']);

        $delta = [];
        foreach ($rows as $row) {
            $delta[$row->memory_id] = ($delta[$row->memory_id] ?? 0.0) + match ($row->verdict) {
                'hit' => 0.05,
                'related' => 0.02,
                'miss' => -0.05,
                default => 0.0,
            };
        }

        foreach ($delta as $id => $boost) {
            $clamped = max(-0.15, min(0.15, $boost));
            $scores[$id] = ($scores[$id] ?? 0.0) + $clamped;
        }
    }
}
