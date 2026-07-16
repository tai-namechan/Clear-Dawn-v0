<?php

namespace App\Domain\Kioku;

use App\Domain\Kioku\Models\Memory;

/**
 * One scored retrieval result from KiokuContextBuilder
 * (docs/architecture/kioku-knowledge-retrieval.md §4).
 *
 * The AI-facing payload() exposes summary-level fields only — never the full
 * raw_content or transcript. The Memory reference stays available for
 * in-process side effects (e.g. RecallService reference counting).
 */
final readonly class KiokuContextItem
{
    private const EXCERPT_CHARS = 200;

    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public Memory $memory,
        public int $score,
        public array $reasons,
    ) {}

    /**
     * summary when present; otherwise the same short excerpt Recall has
     * always used (never the full raw text).
     */
    public function excerpt(): string
    {
        $summary = trim((string) $this->memory->summary);
        if ($summary !== '') {
            return $summary;
        }

        $body = (string) ($this->memory->raw_content ?? $this->memory->transcript_text);

        return mb_substr(trim($body), 0, self::EXCERPT_CHARS);
    }

    /**
     * Character weight used against the maxChars budget.
     */
    public function chars(): int
    {
        return mb_strlen($this->memory->title) + mb_strlen($this->excerpt());
    }

    /**
     * @return array{memory_id: string, title: string, excerpt: string, tags: list<string>, score: int, reasons: list<string>, captured_at: string|null}
     */
    public function payload(): array
    {
        return [
            'memory_id' => $this->memory->id,
            'title' => $this->memory->title,
            'excerpt' => $this->excerpt(),
            'tags' => $this->memory->tags ?? [],
            'score' => $this->score,
            'reasons' => $this->reasons,
            'captured_at' => $this->memory->captured_at?->toDateString(),
        ];
    }
}
