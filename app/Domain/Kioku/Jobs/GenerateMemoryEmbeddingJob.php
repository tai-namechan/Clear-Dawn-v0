<?php

namespace App\Domain\Kioku\Jobs;

use App\Domain\Kioku\Embedding\EmbeddingFailedException;
use App\Domain\Kioku\Embedding\EmbeddingGateway;
use App\Domain\Kioku\Embedding\EmbeddingRequest;
use App\Domain\Kioku\Embedding\SearchDocumentBuilder;
use App\Domain\Kioku\Embedding\VectorStore;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryEmbedding;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateMemoryEmbeddingJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public int $uniqueFor = 3600;

    public function __construct(public string $memoryId) {}

    public function uniqueId(): string
    {
        return $this->memoryId;
    }

    public function handle(
        EmbeddingGateway $gateway,
        SearchDocumentBuilder $builder,
        VectorStore $store,
    ): void {
        if (! config('kioku.embedding.enabled', false)) {
            return;
        }

        if (config('kioku.embedding.provider', 'none') === 'none') {
            return;
        }

        $memory = Memory::query()->withoutUserScope()->find($this->memoryId);
        if ($memory === null) {
            return;
        }

        if ($memory->sensitive || $memory->source_type === 'kioku_letter') {
            $store->deleteForMemory($memory->id, (int) $memory->user_id);

            return;
        }

        $built = $builder->build($memory);
        if ($built === null) {
            return;
        }

        $provider = (string) config('kioku.embedding.provider');
        $embedding = MemoryEmbedding::query()
            ->withoutUserScope()
            ->firstOrNew([
                'memory_id' => $memory->id,
                'provider' => $provider === 'fake' ? 'fake' : 'openai',
                'model' => $built['model'],
                'schema_version' => $built['schema_version'],
            ], [
                'user_id' => $memory->user_id,
                'status' => 'pending',
                'content_hash' => $built['content_hash'],
            ]);

        if (! $embedding->exists) {
            $embedding->user_id = $memory->user_id;
            $embedding->content_hash = $built['content_hash'];
            $embedding->status = 'pending';
            $embedding->save();
        }

        // Same hash already ready → zero API calls.
        if (
            $embedding->status === 'ready'
            && $embedding->content_hash === $built['content_hash']
            && $embedding->vector !== null
        ) {
            return;
        }

        if (! $this->claim($embedding, $built['content_hash'])) {
            return;
        }

        $this->enforceUserCap((int) $memory->user_id, $embedding->id);

        try {
            $result = $gateway->embed(new EmbeddingRequest(
                userId: (int) $memory->user_id,
                memoryId: $memory->id,
                text: $built['document'],
                model: $built['model'],
                dimensions: (int) config('kioku.embedding.dimensions', 1536),
            ));

            // Stale write guard: only the claimed row with matching hash may publish.
            $updated = MemoryEmbedding::query()
                ->withoutUserScope()
                ->whereKey($embedding->id)
                ->where('status', 'processing')
                ->where('content_hash', $built['content_hash'])
                ->update([
                    'vector' => json_encode($result->vector, JSON_THROW_ON_ERROR),
                    'dimensions' => $result->dimensions,
                    'status' => 'ready',
                    'input_tokens' => $result->inputTokens,
                    'actual_usd' => $result->actualUsd,
                    'embedded_at' => now(),
                    'error_code' => null,
                    'provider' => $result->provider,
                    'model' => $result->model,
                ]);

            if ($updated !== 1) {
                Log::info('GenerateMemoryEmbeddingJob stale write rejected', [
                    'memory_id' => $this->memoryId,
                ]);
            }
        } catch (EmbeddingFailedException $e) {
            Log::warning('GenerateMemoryEmbeddingJob failed', [
                'memory_id' => $this->memoryId,
                'error_code' => $e->errorCode,
                'permanent' => $e->permanent,
            ]);

            if ($e->permanent || $this->attempts() >= $this->tries) {
                $this->markFailed($embedding->id, $e->errorCode);

                return;
            }

            MemoryEmbedding::query()
                ->withoutUserScope()
                ->whereKey($embedding->id)
                ->where('status', 'processing')
                ->update(['status' => 'pending', 'error_code' => $e->errorCode]);

            throw $e;
        } catch (Throwable $e) {
            Log::warning('GenerateMemoryEmbeddingJob unexpected failure', [
                'memory_id' => $this->memoryId,
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->markFailed($embedding->id, 'unexpected_error');

                return;
            }

            MemoryEmbedding::query()
                ->withoutUserScope()
                ->whereKey($embedding->id)
                ->where('status', 'processing')
                ->update(['status' => 'pending']);

            throw $e;
        }
    }

    private function claim(MemoryEmbedding $embedding, string $contentHash): bool
    {
        $claimable = $this->attempts() > 1 ? ['pending', 'processing', 'failed'] : ['pending', 'failed', 'ready'];

        $claimed = MemoryEmbedding::query()
            ->withoutUserScope()
            ->whereKey($embedding->id)
            ->whereIn('status', $claimable)
            ->update([
                'status' => 'processing',
                'content_hash' => $contentHash,
                'claimed_at' => now(),
                'error_code' => null,
            ]);

        return $claimed === 1;
    }

    private function markFailed(string $embeddingId, string $errorCode): void
    {
        MemoryEmbedding::query()
            ->withoutUserScope()
            ->whereKey($embeddingId)
            ->whereIn('status', ['pending', 'processing'])
            ->update([
                'status' => 'failed',
                'error_code' => $errorCode,
            ]);
    }

    private function enforceUserCap(int $userId, string $keepId): void
    {
        $cap = (int) config('kioku.embedding.max_memories_per_user', 1000);
        $readyCount = MemoryEmbedding::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('status', 'ready')
            ->count();

        if ($readyCount < $cap) {
            return;
        }

        // Drop oldest ready embeddings beyond cap (excluding the current row).
        $overflow = $readyCount - $cap + 1;
        if ($overflow <= 0) {
            return;
        }

        $ids = MemoryEmbedding::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('status', 'ready')
            ->where('id', '!=', $keepId)
            ->orderBy('embedded_at')
            ->limit($overflow)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            MemoryEmbedding::query()->withoutUserScope()->whereIn('id', $ids)->delete();
        }
    }
}
