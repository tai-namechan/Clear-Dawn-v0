<?php

namespace App\Domain\Kioku\Embedding;

use App\Domain\Kioku\Models\Memory;

/**
 * Single source of truth for the string passed to EmbeddingGateway.
 */
final class SearchDocumentBuilder
{
    public const SCHEMA_VERSION = 'v1';

    /**
     * @return array{document: string, content_hash: string, schema_version: string, model: string}|null
     */
    public function build(Memory $memory, ?string $modelOverride = null): ?array
    {
        if ($memory->sensitive) {
            return null;
        }

        if ($memory->source_type === 'kioku_letter') {
            return null;
        }

        if ($memory->status !== 'ready') {
            return null;
        }

        $schema = (string) config('kioku.embedding.schema_version', self::SCHEMA_VERSION);
        $model = $modelOverride !== null && $modelOverride !== ''
            ? $modelOverride
            : (string) config('kioku.embedding.model', 'text-embedding-3-small');
        $document = $this->normalizedDocument($memory);
        $maxChars = (int) config('kioku.embedding.max_document_chars', 8000);
        if (mb_strlen($document) > $maxChars) {
            $document = mb_substr($document, 0, $maxChars);
        }

        return [
            'document' => $document,
            'content_hash' => hash('sha256', $schema.'|'.$model.'|'.$document),
            'schema_version' => $schema,
            'model' => $model,
        ];
    }

    public function normalizedDocument(Memory $memory): string
    {
        $tags = $memory->tags ?? [];
        $tags = array_values(array_filter(array_map(fn ($t) => trim((string) $t), $tags), fn ($t) => $t !== ''));
        sort($tags, SORT_STRING);

        $structured = is_array($memory->structured_data) ? $memory->structured_data : [];
        $insight = isset($structured['insight']) ? trim((string) $structured['insight']) : '';
        $nextAction = isset($structured['next_action']) ? trim((string) $structured['next_action']) : '';

        $lines = [
            'title: '.$this->line((string) ($memory->title ?? '')),
            'summary: '.$this->line((string) ($memory->summary ?? '')),
            'transcript: '.$this->line((string) ($memory->transcript_text ?? '')),
            'tags: '.implode(', ', $tags),
            'type: '.$this->line((string) ($memory->memory_type ?? '')),
            'insight: '.$this->line($insight),
            'next_action: '.$this->line($nextAction),
        ];

        return implode("\n", $lines);
    }

    private function line(string $value): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($value));

        return preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;
    }
}
