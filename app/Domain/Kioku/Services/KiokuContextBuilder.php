<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\KiokuContextItem;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Retrieval for AI-facing memory context
 * (docs/architecture/kioku-knowledge-retrieval.md §4).
 *
 * Owner, status=ready, sensitive=false and the kioku_letter exclusion are
 * all applied at the SQL stage; the candidate pool is capped in SQL so the
 * whole archive is never loaded into PHP and never handed to the AI.
 * Scoring is deterministic and explainable via per-item reasons. Zero
 * results are a normal outcome. Memory bodies are never logged here.
 */
final class KiokuContextBuilder
{
    public const DEFAULT_TOP_K = 5;

    public const DEFAULT_MAX_CHARS = 4000;

    public const CANDIDATE_LIMIT = 50;

    public const MAX_QUERY_LENGTH = 200;

    public const MAX_TERMS = 8;

    public const CACHE_TTL_SECONDS = 120;

    private const SCORE_TAG_MATCH = 8;

    private const SCORE_TITLE_TERM = 4;

    private const SCORE_SUMMARY_TERM = 2;

    private const SCORE_BODY_TERM = 1;

    private const SCORE_SEED_LINK = 3;

    public function __construct(
        private KiokuTagNormalizer $tagNormalizer,
    ) {}

    /**
     * @param  array<mixed>  $tags
     * @param  list<string>  $seedMemoryIds
     * @return Collection<int, KiokuContextItem>
     */
    public function retrieve(
        int $userId,
        string $query,
        array $tags = [],
        array $seedMemoryIds = [],
        int $topK = self::DEFAULT_TOP_K,
        int $maxChars = self::DEFAULT_MAX_CHARS,
    ): Collection {
        $startTime = hrtime(true);

        $tags = $this->tagNormalizer->normalize($tags);
        $terms = $this->terms($query);
        $linkedIds = $this->linkedIds($userId, $seedMemoryIds);

        if ($terms === [] && $tags === [] && $linkedIds === []) {
            $this->logMetrics($userId, 0, 0, 0, $startTime, true);

            return collect();
        }

        $cacheKey = $this->cacheKey($userId, $terms, $tags, $seedMemoryIds, $topK, $maxChars);

        if ($cacheKey !== null) {
            $cached = Cache::get($cacheKey);
            if ($cached instanceof Collection) {
                $this->logMetrics($userId, $cached->count(), $cached->count(), 0, $startTime, true);

                return $cached;
            }
        }

        $candidates = $this->candidates($userId, $terms, $tags, $linkedIds, $seedMemoryIds);
        $candidateCount = $candidates->count();

        $scored = $candidates
            ->map(fn (Memory $memory): KiokuContextItem => $this->scoreMemory($memory, $terms, $tags, $linkedIds))
            ->filter(fn (KiokuContextItem $item): bool => $item->score > 0)
            ->sort(function (KiokuContextItem $a, KiokuContextItem $b): int {
                if ($a->score !== $b->score) {
                    return $b->score <=> $a->score;
                }

                if ($a->memory->importance !== $b->memory->importance) {
                    return $b->memory->importance <=> $a->memory->importance;
                }

                $aCaptured = $a->memory->captured_at->getTimestamp();
                $bCaptured = $b->memory->captured_at->getTimestamp();
                if ($aCaptured !== $bCaptured) {
                    return $bCaptured <=> $aCaptured;
                }

                return $a->memory->id <=> $b->memory->id;
            })
            ->values();

        $selected = [];
        $usedChars = 0;
        foreach ($scored as $item) {
            if (count($selected) >= $topK) {
                break;
            }

            $chars = $item->chars();
            if ($usedChars + $chars > $maxChars) {
                continue;
            }

            $usedChars += $chars;
            $selected[] = $item;
        }

        $result = collect($selected);

        if ($cacheKey !== null) {
            Cache::put($cacheKey, $result, self::CACHE_TTL_SECONDS);
        }

        $this->logMetrics($userId, $candidateCount, count($selected), (hrtime(true) - $startTime) / 1e6, $startTime, false);

        return $result;
    }

    /**
     * @param  list<string>  $terms
     * @param  list<string>  $tags
     * @param  list<string>  $linkedIds
     * @param  list<string>  $seedMemoryIds
     * @return Collection<int, Memory>
     */
    private function candidates(
        int $userId,
        array $terms,
        array $tags,
        array $linkedIds,
        array $seedMemoryIds,
    ): Collection {
        return Memory::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('status', 'ready')
            ->where('sensitive', false)
            ->where('source_type', '!=', 'kioku_letter')
            ->when($seedMemoryIds !== [], fn (Builder $q) => $q->whereNotIn('id', $seedMemoryIds))
            ->where(function (Builder $q) use ($terms, $tags, $linkedIds): void {
                foreach ($terms as $term) {
                    $like = $this->likeContains($term);
                    // ESCAPE keeps %, _, \ literal on both MySQL and SQLite.
                    $q->orWhereRaw('title like ? escape ?', [$like, '\\'])
                        ->orWhereRaw('summary like ? escape ?', [$like, '\\'])
                        ->orWhereRaw('raw_content like ? escape ?', [$like, '\\'])
                        ->orWhereRaw('transcript_text like ? escape ?', [$like, '\\']);
                }

                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }

                if ($linkedIds !== []) {
                    $q->orWhereIn('id', $linkedIds);
                }
            })
            ->orderByDesc('importance')
            ->orderByDesc('captured_at')
            ->orderBy('id')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();
    }

    /**
     * @param  list<string>  $terms
     * @param  list<string>  $tags
     * @param  list<string>  $linkedIds
     */
    private function scoreMemory(Memory $memory, array $terms, array $tags, array $linkedIds): KiokuContextItem
    {
        $score = 0;
        $reasons = [];

        $memoryTags = collect($memory->tags ?? [])
            ->map(fn (string $tag): string => mb_strtolower($tag));

        foreach ($tags as $tag) {
            if ($memoryTags->contains(mb_strtolower($tag))) {
                $score += self::SCORE_TAG_MATCH;
                $reasons[] = "tag:{$tag}";
            }
        }

        foreach ($terms as $term) {
            if (mb_stripos($memory->title, $term) !== false) {
                $score += self::SCORE_TITLE_TERM;
                $reasons[] = "title:{$term}";
            }

            if ($memory->summary !== null && mb_stripos($memory->summary, $term) !== false) {
                $score += self::SCORE_SUMMARY_TERM;
                $reasons[] = "summary:{$term}";
            }

            $body = (string) ($memory->raw_content ?? $memory->transcript_text);
            if ($body !== '' && mb_stripos($body, $term) !== false) {
                $score += self::SCORE_BODY_TERM;
                $reasons[] = "body:{$term}";
            }
        }

        if (in_array($memory->id, $linkedIds, true)) {
            $score += self::SCORE_SEED_LINK;
            $reasons[] = 'link:seed';
        }

        return new KiokuContextItem(memory: $memory, score: $score, reasons: $reasons);
    }

    /**
     * Whitespace-delimited terms with length/count caps.
     *
     * @return list<string>
     */
    private function terms(string $query): array
    {
        $normalized = mb_substr(trim($query), 0, self::MAX_QUERY_LENGTH);
        $terms = [];

        foreach (preg_split('/[\s　]+/u', $normalized) ?: [] as $rawTerm) {
            $term = trim((string) $rawTerm);
            if ($term === '' || in_array($term, $terms, true)) {
                continue;
            }

            $terms[] = $term;

            if (count($terms) >= self::MAX_TERMS) {
                break;
            }
        }

        return $terms;
    }

    /**
     * @param  list<string>  $terms
     * @param  list<string>  $tags
     * @param  list<string>  $seedMemoryIds
     */
    private function cacheKey(int $userId, array $terms, array $tags, array $seedMemoryIds, int $topK, int $maxChars): ?string
    {
        if ($seedMemoryIds !== []) {
            return null;
        }

        $version = (int) (User::query()->whereKey($userId)->value('memory_version') ?? 0);

        $queryHash = hash('xxh3', implode("\0", $terms));
        $filtersHash = hash('xxh3', json_encode(['tags' => $tags, 'topK' => $topK, 'maxChars' => $maxChars]));

        return "recall:v{$version}:{$userId}:{$queryHash}:{$filtersHash}";
    }

    private function logMetrics(int $userId, int $candidateCount, int $selectedCount, float $elapsedMs, int $startTime, bool $cached): void
    {
        try {
            Log::channel('recall')->info('recall_query', [
                'user_id' => $userId,
                'candidates' => $candidateCount,
                'selected' => $selectedCount,
                'elapsed_ms' => round($cached ? (hrtime(true) - $startTime) / 1e6 : $elapsedMs, 2),
                'cached' => $cached,
            ]);
        } catch (\Throwable) {
            // Metrics logging must never break retrieval
        }
    }

    /**
     * LIKE pattern for substring match with metacharacters escaped.
     */
    private function likeContains(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }

    /**
     * Memories linked to the seed set through memory_links, both directions,
     * owner-checked at scoring time via the candidate query's user_id filter.
     *
     * @param  list<string>  $seedMemoryIds
     * @return list<string>
     */
    private function linkedIds(int $userId, array $seedMemoryIds): array
    {
        if ($seedMemoryIds === []) {
            return [];
        }

        // Seeds must belong to the owner; foreign IDs contribute nothing.
        $ownedSeedIds = Memory::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->whereIn('id', $seedMemoryIds)
            ->pluck('id')
            ->all();

        if ($ownedSeedIds === []) {
            return [];
        }

        $links = MemoryLink::query()
            ->where(function ($query) use ($ownedSeedIds): void {
                $query->whereIn('from_memory_id', $ownedSeedIds)
                    ->orWhereIn('to_memory_id', $ownedSeedIds);
            })
            ->get(['from_memory_id', 'to_memory_id']);

        $ids = [];
        foreach ($links as $link) {
            foreach ([$link->from_memory_id, $link->to_memory_id] as $id) {
                if (in_array($id, $ownedSeedIds, true) || in_array($id, $ids, true)) {
                    continue;
                }

                $ids[] = $id;
            }
        }

        return $ids;
    }
}
