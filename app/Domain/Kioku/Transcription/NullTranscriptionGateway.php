<?php

namespace App\Domain\Kioku\Transcription;

use App\Domain\Kioku\Models\MemoryAsset;
use RuntimeException;

/**
 * Bound when no provider is configured. Callers must guard with
 * config('kioku.transcription.provider') before transcribing; reaching
 * this class is a programming error, never a fake success.
 */
final class NullTranscriptionGateway implements TranscriptionGateway
{
    public function transcribe(MemoryAsset $asset): TranscriptionResult
    {
        throw new RuntimeException('No transcription provider is configured (KIOKU_TRANSCRIPTION_PROVIDER=none).');
    }
}
