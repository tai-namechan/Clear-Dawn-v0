<?php

namespace App\Domain\Kioku\Capture;

use App\Domain\Kioku\Capture\Dto\NormalizedContent;
use App\Domain\Kioku\Models\Memory;

interface RawNormalizer
{
    public function supports(RawKind $kind): bool;

    public function normalize(Memory $memory): NormalizedContent;
}
