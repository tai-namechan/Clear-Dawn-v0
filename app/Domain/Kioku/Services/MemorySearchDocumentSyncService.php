<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Jobs\GenerateMemoryEmbeddingJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryEmbedding;
use Illuminate\Support\Facades\DB;

/**
 * Single write path for interpretation-layer fields that feed SearchDocumentBuilder
 * (title / summary / tags / structured_data / transcript). Commits then invalidates
 * ready embeddings and dispatches regeneration.
 */
final class MemorySearchDocumentSyncService
{
    public function __construct(
        private KiokuTagNormalizer $tagNormalizer,
        private RelatedMemoryService $relatedMemoryService,
    ) {}

    /**
     * Owner tag edit: facts untouched, related links recomputed, embedding refreshed.
     *
     * @param  list<string>|null  $rawTags
     */
    public function updateTags(Memory $memory, ?array $rawTags): Memory
    {
        $tags = $this->tagNormalizer->normalize($rawTags ?? []);

        return $this->commitInterpretationFields($memory, [
            'tags' => $tags === [] ? null : $tags,
        ], recomputeRelated: true);
    }

    /**
     * Generic path for enrichment / transcript / other interpretation writes.
     *
     * @param  array<string, mixed>  $fields
     */
    public function syncFields(Memory $memory, array $fields, bool $recomputeRelated = false): Memory
    {
        $allowed = array_intersect_key($fields, array_flip([
            'title',
            'summary',
            'tags',
            'structured_data',
            'transcript_text',
            'memory_type',
        ]));

        if ($allowed === []) {
            return $memory;
        }

        return $this->commitInterpretationFields($memory, $allowed, $recomputeRelated);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function commitInterpretationFields(Memory $memory, array $fields, bool $recomputeRelated): Memory
    {
        DB::transaction(function () use ($memory, $fields): void {
            $memory->update($fields);
            $this->invalidateReadyEmbeddings($memory);
        });

        $memory->refresh();

        if ($recomputeRelated) {
            $this->relatedMemoryService->cacheRelated($memory);
        }

        $this->dispatchEmbedding($memory);

        return $memory;
    }

    private function invalidateReadyEmbeddings(Memory $memory): void
    {
        MemoryEmbedding::query()
            ->withoutUserScope()
            ->where('memory_id', $memory->id)
            ->where('user_id', $memory->user_id)
            ->where('status', 'ready')
            ->update([
                'status' => 'pending',
                'error_code' => null,
            ]);
    }

    private function dispatchEmbedding(Memory $memory): void
    {
        if (! config('kioku.embedding.enabled', false)) {
            return;
        }

        if (config('kioku.embedding.provider', 'none') === 'none') {
            return;
        }

        if ($memory->sensitive || $memory->source_type === 'kioku_letter' || $memory->status !== 'ready') {
            return;
        }

        GenerateMemoryEmbeddingJob::dispatch($memory->id);
    }
}
