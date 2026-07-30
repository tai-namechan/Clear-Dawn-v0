<?php

namespace App\Domain\Kioku\Capture\Normalizers;

use App\Domain\Kioku\Capture\RawKind;
use App\Domain\Kioku\Capture\RawNormalizer;
use RuntimeException;

final class RawNormalizerRegistry
{
    /**
     * @param  list<RawNormalizer>  $normalizers
     */
    public function __construct(
        private readonly array $normalizers,
    ) {}

    public function for(RawKind $kind): RawNormalizer
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer->supports($kind)) {
                return $normalizer;
            }
        }

        throw new RuntimeException("No RawNormalizer registered for [{$kind->value}].");
    }
}
