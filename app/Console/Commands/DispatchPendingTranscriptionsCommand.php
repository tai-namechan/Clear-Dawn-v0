<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Jobs\TranscribeMemoryAudioJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryAsset;
use Illuminate\Console\Command;

/**
 * After enabling a real transcription provider, re-queue voice memories that
 * were captured while KIOKU_TRANSCRIPTION_PROVIDER=none (they stay pending
 * with durable audio already on disk).
 */
class DispatchPendingTranscriptionsCommand extends Command
{
    protected $signature = 'kioku:transcriptions:dispatch-pending
        {--user= : Limit to a single users.id}
        {--dry-run : List matching memories without dispatching}';

    protected $description = 'Dispatch TranscribeMemoryAudioJob for pending voice memories with an audio original';

    public function handle(): int
    {
        if (config('kioku.transcription.provider', 'none') === 'none') {
            $this->error(
                'KIOKU_TRANSCRIPTION_PROVIDER is none — nothing to dispatch. '
                .'Configure a provider, then re-run this command.',
            );

            return self::FAILURE;
        }

        $query = Memory::query()
            ->withoutUserScope()
            ->where('source_type', 'voice')
            ->where('transcription_status', 'pending')
            ->whereHas('assets', function ($assets): void {
                $assets->where('kind', MemoryAsset::KIND_AUDIO_ORIGINAL);
            })
            ->when(
                $this->option('user') !== null,
                fn ($q) => $q->where('user_id', (int) $this->option('user')),
            )
            ->orderBy('id');

        $matched = 0;
        $dispatched = 0;
        $dryRun = (bool) $this->option('dry-run');

        $query->chunkById(100, function ($memories) use (&$matched, &$dispatched, $dryRun): void {
            foreach ($memories as $memory) {
                $matched++;

                if ($dryRun) {
                    $this->line("dry-run {$memory->id} (user {$memory->user_id})");

                    continue;
                }

                TranscribeMemoryAudioJob::dispatch($memory->id);
                $dispatched++;
            }
        });

        if ($dryRun) {
            $this->info("Dry run: {$matched} pending voice memory(ies) would be dispatched.");

            return self::SUCCESS;
        }

        $this->info("Dispatched {$dispatched} TranscribeMemoryAudioJob(s) for {$matched} pending voice memory(ies).");
        $this->comment('ShouldBeUnique + conditional claim prevent duplicate transcription work on re-run.');

        return self::SUCCESS;
    }
}
