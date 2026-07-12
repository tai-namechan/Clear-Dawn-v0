<?php

namespace App\Domain\Kioku\Transcription;

use App\Domain\Kioku\Models\MemoryAsset;

/**
 * Deterministic gateway for tests and local development
 * (KIOKU_TRANSCRIPTION_PROVIDER=fake). Never used in production.
 */
final class FakeTranscriptionGateway implements TranscriptionGateway
{
    public function transcribe(MemoryAsset $asset): TranscriptionResult
    {
        return new TranscriptionResult(
            text: (string) config('kioku.transcription.fake_text', 'これはテスト用の文字起こしです。'),
            provider: 'fake',
            model: 'fake-v1',
        );
    }
}
