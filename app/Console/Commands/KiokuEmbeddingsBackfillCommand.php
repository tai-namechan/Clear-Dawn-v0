<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Embedding\SearchDocumentBuilder;
use App\Domain\Kioku\Jobs\GenerateMemoryEmbeddingJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryEmbedding;
use Illuminate\Console\Command;

class KiokuEmbeddingsBackfillCommand extends Command
{
    protected $signature = 'kioku:embeddings:backfill
                            {--user= : Required user id}
                            {--dry-run : Report without dispatching}
                            {--limit=100 : Max memories to enqueue}';

    protected $description = 'Enqueue embedding generation for memories that still need a current vector';

    public function handle(SearchDocumentBuilder $builder): int
    {
        $userId = $this->option('user');
        if ($userId === null || $userId === '') {
            $this->error('--user is required for safety.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $userIdInt = (int) $userId;
        $provider = (string) config('kioku.embedding.provider', 'openai') === 'fake' ? 'fake' : 'openai';
        $model = (string) config('kioku.embedding.model', 'text-embedding-3-small');
        $schema = (string) config('kioku.embedding.schema_version', SearchDocumentBuilder::SCHEMA_VERSION);

        $selected = 0;
        $scanned = 0;

        Memory::query()
            ->withoutUserScope()
            ->where('user_id', $userIdInt)
            ->where('status', 'ready')
            ->where('sensitive', false)
            ->where('source_type', '!=', 'kioku_letter')
            ->orderBy('id')
            ->lazyById(100)
            ->each(function (Memory $memory) use (
                $builder,
                $dryRun,
                $limit,
                $provider,
                $model,
                $schema,
                &$selected,
                &$scanned,
            ): ?bool {
                if ($selected >= $limit) {
                    return false;
                }

                $scanned++;
                $built = $builder->build($memory);
                if ($built === null) {
                    return null;
                }

                $existing = MemoryEmbedding::query()
                    ->withoutUserScope()
                    ->where('memory_id', $memory->id)
                    ->where('provider', $provider)
                    ->where('model', $model)
                    ->where('schema_version', $schema)
                    ->first();

                if (
                    $existing !== null
                    && $existing->status === 'ready'
                    && $existing->vector !== null
                    && $existing->content_hash === $built['content_hash']
                ) {
                    return null;
                }

                if (! $dryRun) {
                    GenerateMemoryEmbeddingJob::dispatch($memory->id);
                }

                $selected++;

                return null;
            });

        $this->info(($dryRun ? '[dry-run] ' : '').'scanned='.$scanned.' candidates='.$selected);

        if (! $dryRun) {
            $this->info('dispatched='.$selected);
        }

        return self::SUCCESS;
    }
}
