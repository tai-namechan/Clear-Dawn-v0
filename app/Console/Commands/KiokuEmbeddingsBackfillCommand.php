<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Jobs\GenerateMemoryEmbeddingJob;
use App\Domain\Kioku\Models\Memory;
use Illuminate\Console\Command;

class KiokuEmbeddingsBackfillCommand extends Command
{
    protected $signature = 'kioku:embeddings:backfill
                            {--user= : Required user id}
                            {--dry-run : Report without dispatching}
                            {--limit=100 : Max memories to enqueue}';

    protected $description = 'Enqueue embedding generation for a single user';

    public function handle(): int
    {
        $userId = $this->option('user');
        if ($userId === null || $userId === '') {
            $this->error('--user is required for safety.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        $memories = Memory::query()
            ->withoutUserScope()
            ->where('user_id', (int) $userId)
            ->where('status', 'ready')
            ->where('sensitive', false)
            ->where('source_type', '!=', 'kioku_letter')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id']);

        $this->info(($dryRun ? '[dry-run] ' : '').'candidates='.$memories->count());

        if ($dryRun) {
            return self::SUCCESS;
        }

        foreach ($memories as $memory) {
            GenerateMemoryEmbeddingJob::dispatch($memory->id);
        }

        $this->info('dispatched='.$memories->count());

        return self::SUCCESS;
    }
}
