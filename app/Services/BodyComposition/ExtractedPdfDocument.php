<?php

namespace App\Services\BodyComposition;

/**
 * 位置付きPDFテキスト。
 */
final readonly class ExtractedPdfDocument
{
    /**
     * @param  list<ExtractedPdfTextItem>  $items
     */
    public function __construct(
        public array $items,
        public string $readingOrderText,
    ) {}

    /**
     * プレーンテキストだけの文書。
     */
    public static function fromPlainText(string $text): self
    {
        $items = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $index => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $items[] = new ExtractedPdfTextItem($line, 0.0, (float) (1000 - $index));
        }

        return new self($items, trim($text));
    }
}
