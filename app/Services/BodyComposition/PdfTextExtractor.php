<?php

namespace App\Services\BodyComposition;

/**
 * PDF本文のテキスト抽出。
 *
 * FlateDecode を展開し、Tj/TJ と選択中フォントの ToUnicode だけを読む。
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

        $objects = $this->parseObjects($raw);
        $fontCmaps = $this->fontCmaps($objects);
        $pageContentIds = $this->pageContentObjectIds($objects);
        $items = [];

        foreach ($objects as $id => $object) {
            $fontMap = $this->fontResourceMap($object['dict'], $objects);

            foreach ($this->contentObjectIds($object['dict']) as $contentId) {
                $stream = $objects[$contentId]['stream'] ?? null;

                if ($stream === null || ! $this->hasTextOperators($stream)) {
                    continue;
                }

                foreach ($this->extractPositionedItems($stream, $fontCmaps, $fontMap) as $item) {
                    if ($this->isUsableText($item->text)) {
                        $items[] = $item;
                    }
                }
            }

            $stream = $object['stream'];

            if ($stream === null || isset($pageContentIds[$id]) || ! $this->hasTextOperators($stream)) {
                continue;
            }

            foreach ($this->extractPositionedItems($stream, $fontCmaps, $fontMap) as $item) {
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
     * @return array<int, array{dict: string, stream: string|null}>
     */
    private function parseObjects(string $raw): array
    {
        $objects = [];
        $offset = 0;
        $length = strlen($raw);

        while (preg_match('/(\d+)\s+0\s+obj/', $raw, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $id = (int) $match[1][0];
            $objStart = (int) $match[0][1] + strlen($match[0][0]);
            $endobj = strpos($raw, 'endobj', $objStart);

            if ($endobj === false) {
                break;
            }

            $streamPos = strpos($raw, 'stream', $objStart);

            if ($streamPos === false || $streamPos > $endobj) {
                $objects[$id] = [
                    'dict' => substr($raw, $objStart, $endobj - $objStart),
                    'stream' => null,
                ];
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

            $objects[$id] = [
                'dict' => $header,
                'stream' => $decoded !== null && $decoded !== '' ? $decoded : null,
            ];
            $offset = $endobj + 6;
        }

        return $objects;
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
     * @param  array<int, array{dict: string, stream: string|null}>  $objects
     * @return array<int, array<string, string>>
     */
    private function fontCmaps(array $objects): array
    {
        $cmapsByObject = [];

        foreach ($objects as $id => $object) {
            if ($object['stream'] === null || ! str_contains($object['stream'], 'begincmap')) {
                continue;
            }

            $cmapsByObject[$id] = $this->parseCmap($object['stream']);
        }

        $fontCmaps = [];

        foreach ($objects as $id => $object) {
            if (! preg_match('/\/Type\s*\/Font\b/', $object['dict'])
                || ! preg_match('/\/ToUnicode\s+(\d+)\s+0\s+R/', $object['dict'], $match)) {
                continue;
            }

            $toUnicodeId = (int) $match[1];

            if (isset($cmapsByObject[$toUnicodeId])) {
                $fontCmaps[$id] = $cmapsByObject[$toUnicodeId];
            }
        }

        return $fontCmaps;
    }

    /**
     * @return array<string, string>
     */
    private function parseCmap(string $decoded): array
    {
        $cmap = [];
        $blocks = [];

        if (str_contains($decoded, 'beginbfchar')
            && preg_match_all('/beginbfchar(.*?)endbfchar/s', $decoded, $matches) !== false) {
            $blocks = $matches[1];
        } else {
            $blocks = [$decoded];
        }

        foreach ($blocks as $block) {
            if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER) === false) {
                continue;
            }

            foreach ($pairs as $pair) {
                $cmap[strtoupper($pair[1])] = $pair[2];
            }
        }

        return $cmap;
    }

    /**
     * @param  array<int, array{dict: string, stream: string|null}>  $objects
     * @return array<int, true>
     */
    private function pageContentObjectIds(array $objects): array
    {
        $ids = [];

        foreach ($objects as $object) {
            foreach ($this->contentObjectIds($object['dict']) as $contentId) {
                $ids[$contentId] = true;
            }
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function contentObjectIds(string $dict): array
    {
        if (preg_match('/\/Contents\s*\[(.*?)\]/s', $dict, $match) === 1) {
            if (preg_match_all('/(\d+)\s+0\s+R/', $match[1], $refs) === false) {
                return [];
            }

            return array_map(fn (string $id): int => (int) $id, $refs[1]);
        }

        if (preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $dict, $match) === 1) {
            return [(int) $match[1]];
        }

        return [];
    }

    /**
     * @param  array<int, array{dict: string, stream: string|null}>  $objects
     * @return array<string, int>
     */
    private function fontResourceMap(string $dict, array $objects): array
    {
        if (preg_match('/\/Resources\s+(\d+)\s+0\s+R/', $dict, $match) === 1) {
            $dict = $objects[(int) $match[1]]['dict'] ?? $dict;
        }

        if (preg_match('/\/Font\s+(\d+)\s+0\s+R/', $dict, $match) === 1) {
            return $this->parseNameRefs($objects[(int) $match[1]]['dict'] ?? '');
        }

        if (preg_match('/\/Font\s*<<([^>]*)>>/', $dict, $match) === 1) {
            return $this->parseNameRefs($match[1]);
        }

        return [];
    }

    /**
     * @return array<string, int>
     */
    private function parseNameRefs(string $dict): array
    {
        if (preg_match_all('/\/([A-Za-z0-9_+\-]+)\s+(\d+)\s+0\s+R/', $dict, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $map = [];

        foreach ($matches as $match) {
            $map[$match[1]] = (int) $match[2];
        }

        return $map;
    }

    private function hasTextOperators(string $decoded): bool
    {
        return str_contains($decoded, 'Tj') || str_contains($decoded, 'TJ');
    }

    /**
     * @param  array<int, array<string, string>>  $fontCmaps
     * @param  array<string, int>  $fontMap
     * @return list<ExtractedPdfTextItem>
     */
    private function extractPositionedItems(string $decoded, array $fontCmaps, array $fontMap): array
    {
        $items = [];
        $x = 0.0;
        $y = 0.0;
        $cmap = [];

        if (preg_match_all(
            '/(?:\/([A-Za-z0-9_+\-]+)\s+[0-9.\-]+\s+Tf)|(?:([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s+Tm)|(?:([0-9.\-]+)\s+([0-9.\-]+)\s+Td)|(?:\(((?:\\\\.|[^\\\\)])*)\)\s*Tj)|(?:<([0-9A-Fa-f]+)>\s*Tj)|(?:\[(.*?)\]\s*TJ)/s',
            $decoded,
            $matches,
            PREG_SET_ORDER,
        ) === false) {
            return [];
        }

        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $fontId = $fontMap[$match[1]] ?? null;
                $cmap = $fontId !== null ? ($fontCmaps[$fontId] ?? []) : [];

                continue;
            }

            if (($match[2] ?? '') !== '' && isset($match[6], $match[7])) {
                $x = (float) $match[6];
                $y = (float) $match[7];

                continue;
            }

            if (($match[8] ?? '') !== '' && isset($match[9])) {
                $x += (float) $match[8];
                $y += (float) $match[9];

                continue;
            }

            if (($match[10] ?? '') !== '') {
                $items[] = new ExtractedPdfTextItem($this->unescapePdfLiteral($match[10]), $x, $y);

                continue;
            }

            if (($match[11] ?? '') !== '') {
                $items[] = new ExtractedPdfTextItem($this->decodeHex($match[11], $cmap), $x, $y);

                continue;
            }

            if (($match[12] ?? '') !== '') {
                $items[] = new ExtractedPdfTextItem($this->decodeTjArray($match[12], $cmap), $x, $y);
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
