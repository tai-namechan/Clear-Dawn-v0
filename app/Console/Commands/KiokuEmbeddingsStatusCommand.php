<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryEmbedding;
use Illuminate\Console\Command;

class KiokuEmbeddingsStatusCommand extends Command
{
    protected $signature = 'kioku:embeddings:status {--user= : Limit to a user id}';

    protected $description = 'Show memory embedding status counts';

    public function handle(): int
    {
        $userId = $this->option('user');

        $query = MemoryEmbedding::query()->withoutUserScope()
            ->when($userId !== null, fn ($q) => $q->where('user_id', (int) $userId));

        $counts = $query->clone()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $this->table(['status', 'count'], $counts->map(fn ($c, $s) => [$s, $c])->values()->all());

        $eligible = Memory::query()->withoutUserScope()
            ->where('status', 'ready')
            ->where('sensitive', false)
            ->where('source_type', '!=', 'kioku_letter')
            ->when($userId !== null, fn ($q) => $q->where('user_id', (int) $userId))
            ->count();

        $this->info("eligible_ready_memories={$eligible}");
        $this->info('embedding_enabled='.(config('kioku.embedding.enabled') ? 'true' : 'false'));
        $this->info('provider='.(string) config('kioku.embedding.provider'));

        return self::SUCCESS;
    }
}
