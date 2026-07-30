<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Jobs\GenerateMemoryEmbeddingJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryEmbedding;
use Illuminate\Console\Command;

class KiokuEmbeddingsRebuildCommand extends Command
{
    protected $signature = 'kioku:embeddings:rebuild
                            {--user= : Required user id}
                            {--model= : Model to generate with (not a filter on existing rows)}
                            {--dry-run : Report without writing}';

    protected $description = 'Re-enqueue embedding generation for a single user (optionally with a target model)';

    public function handle(): int
    {
        $userId = $this->option('user');
        if ($userId === null || $userId === '') {
            $this->error('--user is required for safety.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $modelOption = $this->option('model');
        $modelOverride = is_string($modelOption) && $modelOption !== '' ? $modelOption : null;
        $userIdInt = (int) $userId;

        $memoryIds = Memory::query()
            ->withoutUserScope()
            ->where('user_id', $userIdInt)
            ->where('status', 'ready')
            ->where('sensitive', false)
            ->where('source_type', '!=', 'kioku_letter')
            ->orderBy('id')
            ->pluck('id');

        $this->info(($dryRun ? '[dry-run] ' : '').'memories='.$memoryIds->count()
            .($modelOverride !== null ? " model={$modelOverride}" : ''));

        if ($dryRun) {
            return self::SUCCESS;
        }

        if ($modelOverride === null) {
            MemoryEmbedding::query()
                ->withoutUserScope()
                ->where('user_id', $userIdInt)
                ->update([
                    'status' => 'pending',
                    'content_hash' => '',
                    'vector' => null,
                    'error_code' => 'rebuild_requested',
                ]);
        }

        foreach ($memoryIds as $memoryId) {
            GenerateMemoryEmbeddingJob::dispatch((string) $memoryId, $modelOverride);
        }

        $this->info('dispatched='.$memoryIds->count());

        return self::SUCCESS;
    }
}
