<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\KiokuContextItem;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
        $tags = $this->tagNormalizer->normalize($tags);
        $terms = $this->terms($query);
        $linkedIds = $this->linkedIds($userId, $seedMemoryIds);

        if ($terms === [] && $tags === [] && $linkedIds === []) {
            return collect();
        }

        $candidates = $this->candidates($userId, $terms, $tags, $linkedIds, $seedMemoryIds);

        $scored = $candidates
            ->map(fn (Memory $memory): KiokuContextItem => $this->scoreMemory($memory, $terms, $tags, $linkedIds))
            ->filter(fn (KiokuContextItem $item): bool => $item->score > 0)
            ->sort(function (KiokuContextItem $a, KiokuContextItem $b): int {
                return [$b->score, $b->memory->importance, $b->memory->captured_at?->getTimestamp() ?? 0, $a->memory->id]
                    <=> [$a->score, $a->memory->importance, $a->memory->captured_at?->getTimestamp() ?? 0, $b->memory->id];
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
                break;
            }

            $usedChars += $chars;
            $selected[] = $item;
        }

        return collect($selected);
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
                    $like = '%'.addcslashes($term, '%_\\').'%';
                    $q->orWhere('title', 'like', $like)
                        ->orWhere('summary', 'like', $like)
                        ->orWhere('raw_content', 'like', $like)
                        ->orWhere('transcript_text', 'like', $like);
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
            ->filter(fn ($tag) => is_string($tag))
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
     * Whitespace-delimited terms, same premise as KiokuSearchService — no
     * new tokenizer dependency; tag matches carry the strong signal.
     *
     * @return list<string>
     */
    private function terms(string $query): array
    {
        return collect(preg_split('/[\s　]+/u', trim($query)) ?: [])
            ->map(fn ($term): string => trim((string) $term))
            ->filter(fn (string $term): bool => $term !== '')
            ->unique()
            ->values()
            ->all();
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
            ->whereIn('from_memory_id', $ownedSeedIds)
            ->orWhereIn('to_memory_id', $ownedSeedIds)
            ->get(['from_memory_id', 'to_memory_id']);

        return $links
            ->flatMap(fn (MemoryLink $link) => [$link->from_memory_id, $link->to_memory_id])
            ->unique()
            ->reject(fn (string $id): bool => in_array($id, $ownedSeedIds, true))
            ->values()
            ->all();
    }
}
