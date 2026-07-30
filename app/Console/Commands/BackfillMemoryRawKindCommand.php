<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Capture\CaptureChannel;
use App\Domain\Kioku\Capture\RawKind;
use App\Domain\Kioku\Models\Memory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfills raw_kind / capture_channel from legacy source_type.
 * Unknown source_type values are reported and left null (no guessing).
 */
class BackfillMemoryRawKindCommand extends Command
{
    protected $signature = 'kioku:capture:backfill-raw-kind
                            {--dry-run : Report counts without writing}
                            {--user= : Limit to a single user id}
                            {--limit=1000 : Max rows to update per run}';

    protected $description = 'Backfill memories.raw_kind and capture_channel from source_type';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->option('user');
        $limit = max(1, (int) $this->option('limit'));

        $query = Memory::query()
            ->withoutUserScope()
            ->where(function ($q): void {
                $q->whereNull('raw_kind')->orWhereNull('capture_channel');
            })
            ->when($userId !== null, fn ($q) => $q->where('user_id', (int) $userId))
            ->orderBy('id')
            ->limit($limit);

        $rows = $query->get(['id', 'user_id', 'source_type', 'raw_kind', 'capture_channel', 'raw_content']);
        $mapped = 0;
        $skipped = 0;
        $unknown = [];

        foreach ($rows as $memory) {
            $mapping = $this->mapSourceType((string) $memory->source_type, $memory);
            if ($mapping === null) {
                $skipped++;
                $unknown[$memory->source_type] = ($unknown[$memory->source_type] ?? 0) + 1;

                continue;
            }

            $mapped++;
            if ($dryRun) {
                continue;
            }

            DB::table('memories')->where('id', $memory->id)->update([
                'raw_kind' => $mapping['raw_kind'],
                'capture_channel' => $mapping['capture_channel'],
                'updated_at' => now(),
            ]);
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."mapped={$mapped} skipped={$skipped} scanned={$rows->count()}");
        foreach ($unknown as $type => $count) {
            $this->warn("unknown source_type [{$type}]: {$count}");
        }

        return self::SUCCESS;
    }

    /**
     * @return array{raw_kind: string, capture_channel: string}|null
     */
    private function mapSourceType(string $sourceType, Memory $memory): ?array
    {
        return match ($sourceType) {
            'manual' => [
                'raw_kind' => RawKind::Text->value,
                'capture_channel' => CaptureChannel::WebText->value,
            ],
            'url' => [
                'raw_kind' => RawKind::Url->value,
                'capture_channel' => CaptureChannel::WebUrl->value,
            ],
            'voice' => [
                'raw_kind' => RawKind::Audio->value,
                'capture_channel' => CaptureChannel::BrowserVoice->value,
            ],
            'kioku_letter' => [
                'raw_kind' => RawKind::Text->value,
                'capture_channel' => CaptureChannel::SystemGenerated->value,
            ],
            'yoyu', 'clear_dawn', 'ai_chat', 'slack' => $this->auditConnector($memory),
            default => null,
        };
    }

    /**
     * @return array{raw_kind: string, capture_channel: string}|null
     */
    private function auditConnector(Memory $memory): ?array
    {
        if ($memory->raw_content !== null && trim((string) $memory->raw_content) !== '') {
            return [
                'raw_kind' => RawKind::Text->value,
                'capture_channel' => CaptureChannel::SystemConnector->value,
            ];
        }

        return null;
    }
}
