<?php

namespace App\Domain\Kioku\Export;

use App\Domain\Kioku\Models\Memory;

final class MemoryMarkdownExporter
{
    /**
     * @param  list<Memory>  $memories
     * @return array<string, string> filename => markdown body
     */
    public function exportMany(array $memories, bool $includeTranscript = false, bool $includeSensitive = false): array
    {
        $files = [];
        foreach ($memories as $memory) {
            if ($memory->sensitive && ! $includeSensitive) {
                continue;
            }
            $slug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', mb_substr($memory->title ?: $memory->id, 0, 40)) ?: $memory->id;
            $files[$slug.'-'.$memory->id.'.md'] = $this->toMarkdown($memory, $includeTranscript);
        }

        return $files;
    }

    public function toMarkdown(Memory $memory, bool $includeTranscript = false): string
    {
        $tags = $memory->tags ?? [];
        $tagYaml = $tags === []
            ? '  []'
            : "\n".implode("\n", array_map(static fn ($t) => '  - '.str_replace(["\n", "\r"], '', (string) $t), $tags));

        $structured = is_array($memory->structured_data) ? $memory->structured_data : [];
        $insight = (string) ($structured['insight'] ?? '');
        $next = (string) ($structured['next_action'] ?? '');

        $createdAt = $memory->captured_at->toIso8601String();
        $front = <<<YAML
---
kioku_id: {$memory->id}
created_at: {$createdAt}
type: {$memory->memory_type}
tags:{$tagYaml}
source: {$memory->source_type}
raw_kind: {$memory->raw_kind}
capture_channel: {$memory->capture_channel}
sensitive: {$this->bool($memory->sensitive)}
---

YAML;

        $body = '# '.($memory->title ?: 'Untitled')."\n\n";
        if ($memory->summary) {
            $body .= "## Summary\n\n".$memory->summary."\n\n";
        }
        if ($insight !== '') {
            $body .= "## Insight\n\n".$insight."\n\n";
        }
        if ($next !== '') {
            $body .= "## Next action\n\n".$next."\n\n";
        }
        if ($includeTranscript && $memory->transcript_text) {
            $body .= "## Transcript (derived)\n\n".$memory->transcript_text."\n\n";
        }
        $body .= "## Canonical\n\n";
        $body .= '- Memory ID: `'.$memory->id."`\n";
        $body .= '- Original link: `/kioku/memories/'.$memory->id."`\n";
        if ($memory->raw_kind === 'url' || $memory->source_type === 'url') {
            $body .= '- URL raw: '.$memory->raw_content."\n";
        }
        $body .= "\n> raw_content / 原音声はキオク側が source of truth。このファイルは派生出力です。\n";

        return $front.$body;
    }

    private function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
