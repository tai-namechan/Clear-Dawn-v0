<?php

namespace App\Domain\Kioku\Services;

/**
 * Single normalization path for interpretation-layer tags
 * (docs/architecture/kioku-knowledge-retrieval.md §2). Both AI
 * classification and manual user edits go through here, so search can rely
 * on element-exact matching. Pure string work only — facts (raw_content,
 * transcript_text, audio originals) are never touched.
 */
final class KiokuTagNormalizer
{
    public const MAX_TAGS = 8;

    public const MAX_TAG_CHARS = 40;

    /**
     * @param  array<mixed>  $tags
     * @return list<string>
     */
    public function normalize(array $tags): array
    {
        $normalized = [];
        $seen = [];

        foreach ($tags as $tag) {
            if (! is_string($tag)) {
                continue;
            }

            $tag = $this->cleanup($tag);
            if ($tag === '' || mb_strlen($tag) > self::MAX_TAG_CHARS) {
                // Over-long tags are dropped, never truncated: truncation
                // would silently merge distinct tags into one.
                continue;
            }

            $key = mb_strtolower($tag);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $tag;

            if (count($normalized) === self::MAX_TAGS) {
                break;
            }
        }

        return $normalized;
    }

    private function cleanup(string $tag): string
    {
        $tag = (string) preg_replace('/^[\s　]+|[\s　]+$/u', '', $tag);
        $tag = (string) preg_replace('/[\s　]+/u', ' ', $tag);
        $tag = (string) preg_replace('/^[#＃]+/u', '', $tag);

        return (string) preg_replace('/^[\s　]+|[\s　]+$/u', '', $tag);
    }
}
