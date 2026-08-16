<?php

namespace App\Services\BodyComposition;

/**
 * PDF本文のテキスト抽出。
 *
 * FlateDecode を展開し、Tj/TJ と ToUnicode だけを読む。
 * ファイル全体の括弧拾いはフォント辞書の Height を誤抽出するため使わない。
 */
class PdfTextExtractor
{
    /**
     * PDFファイルから表示テキストを取り出す。
     */
    public function extract(string $absolutePath): string
    {
        return $this->extractDocument($absolutePath)->readingOrderText;
    }

    /**
     * 位置付きテキストを含めた抽出結果。
     */
    public function extractDocument(string $absolutePath): ExtractedPdfDocument
    {
        $raw = file_get_contents($absolutePath);

        if ($raw === false || $raw === '') {
            return new ExtractedPdfDocument([], '');
        }

        $streams = $this->inflateStreams($raw);
        $cmap = $this->mergeCmaps($streams);
        $items = [];

        foreach ($streams as $decoded) {
            if (! str_contains($decoded, 'Tj') && ! str_contains($decoded, 'TJ')) {
                continue;
            }

            foreach ($this->extractPositionedItems($decoded, $cmap) as $item) {
                if ($this->isUsableText($item->text)) {
                    $items[] = $item;
                }
            }
        }

        if ($items === []) {
            $fallback = $this->extractWithPdftotext($absolutePath);

            if ($fallback !== null && trim($fallback) !== '') {
                return ExtractedPdfDocument::fromPlainText($fallback);
            }
        }

        return new ExtractedPdfDocument($items, $this->toReadingOrder($items));
    }

    /**
     * @return list<string>
     */
    private function inflateStreams(string $raw): array
    {
        $streams = [];
        $offset = 0;
        $length = strlen($raw);

        while (preg_match('/\d+\s+0\s+obj/', $raw, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $objStart = (int) $match[0][1] + strlen($match[0][0]);
            $endobj = strpos($raw, 'endobj', $objStart);

            if ($endobj === false) {
                break;
            }

            $streamPos = strpos($raw, 'stream', $objStart);

            if ($streamPos === false || $streamPos > $endobj) {
                $offset = $endobj + 6;

                continue;
            }

            $header = substr($raw, $objStart, $streamPos - $objStart);
            $dataStart = $streamPos + 6;

            if ($dataStart < $length && $raw[$dataStart] === "\r") {
                $dataStart++;
            }

            if ($dataStart < $length && $raw[$dataStart] === "\n") {
                $dataStart++;
            }

            $endstream = strpos($raw, 'endstream', $dataStart);

            if ($endstream === false || $endstream > $endobj) {
                $offset = $endobj + 6;

                continue;
            }

            $data = substr($raw, $dataStart, $endstream - $dataStart);
            $decoded = str_contains($header, 'FlateDecode')
                ? $this->inflate($data)
                : $data;

            if ($decoded !== null && $decoded !== '') {
                $streams[] = $decoded;
            }

            $offset = $endobj + 6;
        }

        return $streams;
    }

    private function inflate(string $data): ?string
    {
        $decoded = @zlib_decode($data);

        if (is_string($decoded) && $decoded !== '') {
            return $decoded;
        }

        $decoded = @gzinflate($data);

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }

    /**
     * @param  list<string>  $streams
     * @return array<string, string>
     */
    private function mergeCmaps(array $streams): array
    {
        $cmap = [];

        foreach ($streams as $decoded) {
            if (! str_contains($decoded, 'begincmap')) {
                continue;
            }

            if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $decoded, $matches, PREG_SET_ORDER) === false) {
                continue;
            }

            foreach ($matches as $match) {
                $cmap[strtoupper($match[1])] = $match[2];
            }
        }

        return $cmap;
    }

    /**
     * @param  array<string, string>  $cmap
     * @return list<ExtractedPdfTextItem>
     */
    private function extractPositionedItems(string $decoded, array $cmap): array
    {
        $items = [];
        $x = 0.0;
        $y = 0.0;

        if (preg_match_all(
            '/(?:([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s+Tm)|(?:([0-9.\-]+)\s+([0-9.\-]+)\s+Td)|(?:\(((?:\\\\.|[^\\\\)])*)\)\s*Tj)|(?:<([0-9A-Fa-f]+)>\s*Tj)|(?:\[(.*?)\]\s*TJ)/s',
            $decoded,
            $matches,
            PREG_SET_ORDER,
        ) === false) {
            return [];
        }

        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '' && isset($match[5], $match[6])) {
                $x = (float) $match[5];
                $y = (float) $match[6];

                continue;
            }

            if (($match[7] ?? '') !== '' && isset($match[8])) {
                $x += (float) $match[7];
                $y += (float) $match[8];

                continue;
            }

            if (($match[9] ?? '') !== '') {
                $items[] = new ExtractedPdfTextItem($this->unescapePdfLiteral($match[9]), $x, $y);

                continue;
            }

            if (($match[10] ?? '') !== '') {
                $items[] = new ExtractedPdfTextItem($this->decodeHex($match[10], $cmap), $x, $y);

                continue;
            }

            if (($match[11] ?? '') !== '') {
                $items[] = new ExtractedPdfTextItem($this->decodeTjArray($match[11], $cmap), $x, $y);
            }
        }

        return $items;
    }

    /**
     * @param  array<string, string>  $cmap
     */
    private function decodeTjArray(string $array, array $cmap): string
    {
        $parts = [];

        if (preg_match_all('/\(((?:\\\\.|[^\\\\)])*)\)|<([0-9A-Fa-f]+)>/', $array, $matches, PREG_SET_ORDER) === false) {
            return '';
        }

        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $parts[] = $this->unescapePdfLiteral($match[1]);

                continue;
            }

            if (($match[2] ?? '') !== '') {
                $parts[] = $this->decodeHex($match[2], $cmap);
            }
        }

        return implode('', $parts);
    }

    /**
     * @param  array<string, string>  $cmap
     */
    private function decodeHex(string $hex, array $cmap): string
    {
        $hex = strtoupper($hex);
        $length = strlen($hex);
        $out = '';
        $i = 0;

        while ($i < $length) {
            $chunk4 = substr($hex, $i, 4);
            $chunk2 = substr($hex, $i, 2);

            if (strlen($chunk4) === 4 && isset($cmap[$chunk4])) {
                $out .= $this->hexToUtf16($cmap[$chunk4]);
                $i += 4;

                continue;
            }

            if (isset($cmap[$chunk2])) {
                $out .= $this->hexToUtf16($cmap[$chunk2]);
                $i += 2;

                continue;
            }

            if (strlen($chunk4) === 4) {
                $out .= $this->hexToUtf16($chunk4);
                $i += 4;

                continue;
            }

            $i += 2;
        }

        return $out;
    }

    private function hexToUtf16(string $hex): string
    {
        $chars = '';
        $length = strlen($hex);

        for ($i = 0; $i + 3 < $length; $i += 4) {
            $code = intval(substr($hex, $i, 4), 16);

            if ($code === 0) {
                continue;
            }

            $chars .= mb_chr($code, 'UTF-8');
        }

        return $chars;
    }

    private function unescapePdfLiteral(string $text): string
    {
        return str_replace(
            ['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'],
            ['(', ')', '\\', "\n", "\r", "\t"],
            $text,
        );
    }

    private function isUsableText(string $text): bool
    {
        $text = trim($text);

        if ($text === '' || strlen($text) > 200) {
            return false;
        }

        $length = mb_strlen($text);
        $printable = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1);
            $code = mb_ord($char, 'UTF-8');

            if ($code >= 32 || $char === "\n" || $char === "\t") {
                $printable++;
            }
        }

        return $printable / max(1, $length) >= 0.8;
    }

    /**
     * @param  list<ExtractedPdfTextItem>  $items
     */
    private function toReadingOrder(array $items): string
    {
        $lines = [];

        foreach ($this->clusterRows($items, 6.0) as $row) {
            usort($row, fn (ExtractedPdfTextItem $a, ExtractedPdfTextItem $b): int => $a->x <=> $b->x);
            $line = trim(implode(' ', array_map(
                fn (ExtractedPdfTextItem $item): string => trim($item->text),
                $row,
            )));

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<ExtractedPdfTextItem>  $items
     * @return list<list<ExtractedPdfTextItem>>
     */
    private function clusterRows(array $items, float $tolerance): array
    {
        usort($items, function (ExtractedPdfTextItem $a, ExtractedPdfTextItem $b): int {
            $y = $b->y <=> $a->y;

            return $y !== 0 ? $y : $a->x <=> $b->x;
        });

        $rows = [];

        foreach ($items as $item) {
            $placed = false;

            foreach ($rows as $index => $row) {
                if (abs($row[0]->y - $item->y) <= $tolerance) {
                    $rows[$index][] = $item;
                    $placed = true;

                    break;
                }
            }

            if (! $placed) {
                $rows[] = [$item];
            }
        }

        return $rows;
    }

    private function extractWithPdftotext(string $absolutePath): ?string
    {
        $binary = trim((string) shell_exec('command -v pdftotext'));

        if ($binary === '') {
            return null;
        }

        $output = [];
        $code = 0;
        exec(
            escapeshellarg($binary).' -enc UTF-8 -layout '.escapeshellarg($absolutePath).' -',
            $output,
            $code,
        );

        if ($code !== 0) {
            return null;
        }

        return implode("\n", $output);
    }
}
