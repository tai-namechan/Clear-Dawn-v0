<?php

namespace App\Domain\Kioku\Jobs;

use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Transcription\TranscriptionFailedException;
use App\Domain\Kioku\Transcription\TranscriptionGateway;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Transcribes the audio original into transcript_text (derived data),
 * then hands off to EnrichMemoryJob. Follows the same conditional-UPDATE
 * claim as EnrichMemoryJob so stale or duplicate runs cannot overwrite a
 * newer result. Never touches raw data: the audio asset stays untouched
 * on every failure path.
 */
class TranscribeMemoryAudioJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 90];

    public int $uniqueFor = 3600;

    public function __construct(public string $memoryId) {}

    public function uniqueId(): string
    {
        return $this->memoryId;
    }

    public function handle(TranscriptionGateway $gateway): void
    {
        $memory = Memory::query()->withoutUserScope()->find($this->memoryId);
        if ($memory === null || $memory->source_type !== 'voice') {
            return;
        }

        if (in_array($memory->transcription_status, ['ready', 'failed', null], true)) {
            return;
        }

        // Provider not configured: stay 'pending' (UI shows "not set up").
        // The audio original is already durable.
        if (config('kioku.transcription.provider', 'none') === 'none') {
            return;
        }

        if (! $this->claim($memory)) {
            return;
        }

        $asset = $memory->audioAsset();
        if ($asset === null) {
            $this->markFailed();

            return;
        }

        try {
            $result = $gateway->transcribe($asset);

            // Conditional write: only the run that still owns 'processing'
            // may publish its transcript, so a stale retry cannot clobber.
            $written = Memory::query()
                ->withoutUserScope()
                ->whereKey($memory->id)
                ->where('transcription_status', 'processing')
                ->update([
                    'transcript_text' => $result->text,
                    'transcription_status' => 'ready',
                ]);

            if ($written === 1) {
                EnrichMemoryJob::dispatch($memory->id);
            }
        } catch (TranscriptionFailedException $e) {
            // Permanent failure (configuration / unsupported input / provider
            // rejection): retrying cannot succeed and would only re-bill, so
            // surface the failure now. Audio original and raw stay intact.
            Log::warning('TranscribeMemoryAudioJob failed permanently', [
                'memory_id' => $this->memoryId,
                'attempt' => $this->attempts(),
                'message' => $e->getMessage(),
            ]);

            $this->markFailed();

            return;
        } catch (Throwable $e) {
            Log::warning('TranscribeMemoryAudioJob failed', [
                'memory_id' => $this->memoryId,
                'attempt' => $this->attempts(),
                'message' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->markFailed();

                return;
            }

            // Release the claim so the retry can pick it up.
            Memory::query()
                ->withoutUserScope()
                ->whereKey($memory->id)
                ->where('transcription_status', 'processing')
                ->update(['transcription_status' => 'pending']);

            throw $e;
        }
    }

    /**
     * Safety net for timeouts / killed workers: surface the failure while
     * keeping the audio asset and every raw field intact.
     */
    public function failed(?Throwable $exception): void
    {
        Memory::query()
            ->withoutUserScope()
            ->whereKey($this->memoryId)
            ->whereIn('transcription_status', ['pending', 'processing'])
            ->update([
                'transcription_status' => 'failed',
                'status' => 'failed',
            ]);
    }

    /**
     * Atomically claim the memory so only one worker transcribes it.
     * First attempt only claims 'pending'; retries may also reclaim
     * 'processing' left behind by a timed-out earlier attempt.
     */
    private function claim(Memory $memory): bool
    {
        $claimable = $this->attempts() > 1 ? ['pending', 'processing'] : ['pending'];

        $claimed = Memory::query()
            ->withoutUserScope()
            ->whereKey($memory->id)
            ->whereIn('transcription_status', $claimable)
            ->update(['transcription_status' => 'processing']);

        return $claimed === 1;
    }

    private function markFailed(): void
    {
        // Overall lifecycle fails too so status polling reaches a terminal
        // state; retry-transcription resets both.
        Memory::query()
            ->withoutUserScope()
            ->whereKey($this->memoryId)
            ->update([
                'transcription_status' => 'failed',
                'status' => 'failed',
            ]);
    }
}
