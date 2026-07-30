<?php

namespace App\Domain\Kioku\Capture\Normalizers;

use App\Domain\Kioku\Capture\Dto\NormalizedContent;
use App\Domain\Kioku\Capture\RawKind;
use App\Domain\Kioku\Capture\RawNormalizer;
use App\Domain\Kioku\Models\Memory;
use RuntimeException;

/**
 * Audio normalization is async (transcription). This normalizer only returns
 * an already-ready transcript; the pipeline dispatches TranscribeMemoryAudioJob.
 */
final class AudioTranscriptionNormalizer implements RawNormalizer
{
    public function supports(RawKind $kind): bool
    {
        return $kind === RawKind::Audio;
    }

    public function normalize(Memory $memory): NormalizedContent
    {
        $text = trim((string) $memory->transcript_text);
        if ($text === '') {
            throw new RuntimeException('Audio memory transcript is not ready.');
        }

        return new NormalizedContent(text: $text, source: 'transcript_text');
    }
}
