<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Jobs\GenerateMemoryEmbeddingJob;
use App\Domain\Kioku\Models\MemoryEmbedding;
use Illuminate\Console\Command;

class KiokuEmbeddingsRebuildCommand extends Command
{
    protected $signature = 'kioku:embeddings:rebuild
                            {--user= : Required user id}
                            {--model= : Optional model override}
                            {--dry-run : Report without writing}';

    protected $description = 'Mark embeddings stale and re-enqueue for a single user';

    public function handle(): int
    {
        $userId = $this->option('user');
        if ($userId === null || $userId === '') {
            $this->error('--user is required for safety.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $model = $this->option('model');

        $query = MemoryEmbedding::query()
            ->withoutUserScope()
            ->where('user_id', (int) $userId)
            ->when(is_string($model) && $model !== '', fn ($q) => $q->where('model', $model));

        $count = $query->count();
        $this->info(($dryRun ? '[dry-run] ' : '')."rows={$count}");

        if ($dryRun) {
            return self::SUCCESS;
        }

        $ids = $query->pluck('memory_id')->unique()->values();
        $query->update([
            'status' => 'pending',
            'content_hash' => '',
            'vector' => null,
            'error_code' => 'rebuild_requested',
        ]);

        foreach ($ids as $memoryId) {
            GenerateMemoryEmbeddingJob::dispatch((string) $memoryId);
        }

        $this->info('dispatched='.$ids->count());

        return self::SUCCESS;
    }
}
