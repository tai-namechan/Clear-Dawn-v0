<?php

namespace App\Domain\Kioku\Capture\Normalizers;

use App\Domain\Kioku\Capture\Dto\NormalizedContent;
use App\Domain\Kioku\Capture\RawKind;
use App\Domain\Kioku\Capture\RawNormalizer;
use App\Domain\Kioku\Models\Memory;
use RuntimeException;

final class TextRawNormalizer implements RawNormalizer
{
    public function supports(RawKind $kind): bool
    {
        return $kind === RawKind::Text;
    }

    public function normalize(Memory $memory): NormalizedContent
    {
        $text = trim((string) $memory->raw_content);
        if ($text === '') {
            throw new RuntimeException('Text memory has empty raw_content.');
        }

        return new NormalizedContent(text: $text, source: 'raw_content');
    }
}
