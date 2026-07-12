<?php

namespace App\Domain\Kioku\Transcription;

use App\Domain\Kioku\Models\MemoryAsset;

/**
 * Swap boundary for speech-to-text providers. The real provider is not yet
 * chosen (docs/product/kioku-quick-capture.md §12); production runs with
 * provider 'none', which stores audio and leaves transcription pending.
 * When a provider is adopted, integrate its usage tracking with the
 * existing AiUsage ledger.
 */
interface TranscriptionGateway
{
    /**
     * @throws \RuntimeException on provider failure
     */
    public function transcribe(MemoryAsset $asset): TranscriptionResult;
}
