<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\KiokuContextItem;
use App\Domain\Kioku\Models\Memory;
use Illuminate\Support\Collection;

/**
 * Past-only recall for AI context injection (Yoyu / Clear Dawn).
 *
 * Candidate retrieval is delegated to KiokuContextBuilder
 * (docs/architecture/kioku-knowledge-retrieval.md §4): owner / ready /
 * sensitive are enforced at the SQL stage, results come back in relevance
 * order under the builder's topK + maxChars limits, and each retrieval is
 * explainable via contextItems(). The public interface, line format, and
 * referenced_count side effect are unchanged.
 */
final class RecallService
{
    public function __construct(
        private KiokuContextBuilder $contextBuilder,
    ) {}

    /**
     * @return list<string>
     */
    public function for(int $userId, string $contextText, int $k = 5, bool $countReference = true): array
    {
        /** @var list<string> $lines */
        $lines = array_values($this->memories($userId, $contextText, $k, $countReference)
            ->map(function (Memory $memory): string {
                $when = $memory->captured_at->diffForHumans();
                $type = $memory->memory_type ?? 'memory';
                $text = $memory->summary ?: mb_substr((string) ($memory->raw_content ?? $memory->transcript_text), 0, 200);

                return "[{$when}/{$type}] {$text}";
            })
            ->all());

        return $lines;
    }

    /**
     * @return Collection<int, Memory>
     */
    public function memories(int $userId, string $contextText, int $k = 5, bool $countReference = true): Collection
    {
        /** @var Collection<int, Memory> $memories */
        $memories = $this->contextItems($userId, $contextText, $k)
            ->map(fn (KiokuContextItem $item): Memory => $item->memory)
            ->unique('id')
            ->values();

        if ($countReference) {
            // The builder already dedupes, so one recall never increments
            // the same memory twice.
            foreach ($memories as $memory) {
                $memory->increment('referenced_count');
            }
        }

        return $memories;
    }

    /**
     * Scored retrieval with match reasons, for internal tracing.
     *
     * @return Collection<int, KiokuContextItem>
     */
    public function contextItems(int $userId, string $contextText, int $k = 5): Collection
    {
        return $this->contextBuilder->retrieve(
            userId: $userId,
            query: $contextText,
            topK: $k,
        );
    }
}
