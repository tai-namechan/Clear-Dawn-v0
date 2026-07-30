<?php

namespace App\Domain\Kioku\Capture\Normalizers;

use App\Domain\Kioku\Capture\Dto\NormalizedContent;
use App\Domain\Kioku\Capture\RawKind;
use App\Domain\Kioku\Capture\RawNormalizer;
use App\Domain\Kioku\Models\Memory;
use RuntimeException;

/**
 * MVP: enrich from the canonical URL string itself.
 * Optional SSRF-safe body fetch is feature-flagged separately and must never
 * replace raw_content.
 */
final class UrlContentNormalizer implements RawNormalizer
{
    public function supports(RawKind $kind): bool
    {
        return $kind === RawKind::Url;
    }

    public function normalize(Memory $memory): NormalizedContent
    {
        $url = trim((string) $memory->raw_content);
        if ($url === '') {
            throw new RuntimeException('URL memory has empty raw_content.');
        }

        return new NormalizedContent(text: $url, source: 'url_raw');
    }
}
