<?php

namespace App\Services\BodyComposition;

/**
 * PDF上の1テキスト片。
 */
final readonly class ExtractedPdfTextItem
{
    public function __construct(
        public string $text,
        public float $x,
        public float $y,
    ) {}
}
