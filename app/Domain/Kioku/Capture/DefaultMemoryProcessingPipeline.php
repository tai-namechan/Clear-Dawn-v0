<?php

namespace App\Domain\Kioku\Capture;

use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Jobs\TranscribeMemoryAudioJob;
use App\Domain\Kioku\Models\Memory;
use Illuminate\Support\Facades\Log;

/**
 * Routes post-capture work by raw_kind (with source_type fallback).
 * Embedding stage is dispatched only when the embedding feature flag is on
 * and a GenerateMemoryEmbeddingJob class is available.
 */
final class DefaultMemoryProcessingPipeline implements MemoryProcessingPipeline
{
    public function dispatchAfterCapture(Memory $memory): void
    {
        $this->resumeFrom($memory, ProcessingStage::Normalize);
    }

    public function resumeFrom(Memory $memory, ProcessingStage $stage): void
    {
        match ($stage) {
            ProcessingStage::Normalize => $this->dispatchNormalize($memory),
            ProcessingStage::Enrich => $this->dispatchEnrich($memory),
            ProcessingStage::Embedding => $this->dispatchEmbedding($memory),
        };
    }

    private function dispatchNormalize(Memory $memory): void
    {
        if ($this->isAudio($memory)) {
            if (config('kioku.transcription.provider', 'none') === 'none') {
                return;
            }

            TranscribeMemoryAudioJob::dispatch($memory->id)->afterCommit();

            return;
        }

        $this->dispatchEnrich($memory);
    }

    private function dispatchEnrich(Memory $memory): void
    {
        EnrichMemoryJob::dispatch($memory->id)->afterCommit();
    }

    private function dispatchEmbedding(Memory $memory): void
    {
        if (! config('kioku.embedding.enabled', false)) {
            return;
        }

        $jobClass = 'App\\Domain\\Kioku\\Jobs\\GenerateMemoryEmbeddingJob';
        if (! class_exists($jobClass)) {
            Log::debug('Embedding stage skipped: GenerateMemoryEmbeddingJob not present', [
                'memory_id' => $memory->id,
            ]);

            return;
        }

        $jobClass::dispatch($memory->id)->afterCommit();
    }

    private function isAudio(Memory $memory): bool
    {
        if ($memory->raw_kind === RawKind::Audio->value) {
            return true;
        }

        return $memory->raw_kind === null && $memory->source_type === 'voice';
    }
}
