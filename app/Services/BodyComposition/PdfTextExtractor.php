<?php

namespace App\Services\BodyComposition;

/**
 * PDF本文のテキスト抽出。
 *
 * 追加パッケージを使わず、pdftotext があればそれを優先する。
 */
class PdfTextExtractor
{
    /**
     * PDFファイルから表示テキストを取り出す。
     */
    public function extract(string $absolutePath): string
    {
        $fromBinary = $this->extractWithPdftotext($absolutePath);

        if ($fromBinary !== null && trim($fromBinary) !== '') {
            return $fromBinary;
        }

        $raw = file_get_contents($absolutePath);

        if ($raw === false) {
            return '';
        }

        return $this->extractFromContentStreams($raw);
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
            escapeshellarg($binary).' -enc UTF-8 '.escapeshellarg($absolutePath).' -',
            $output,
            $code,
        );

        if ($code !== 0) {
            return null;
        }

        return implode("\n", $output);
    }

    private function extractFromContentStreams(string $raw): string
    {
        preg_match_all('/\\((?:\\\\.|[^\\\\)])*\\)/', $raw, $matches);

        $parts = [];

        foreach ($matches[0] as $token) {
            $inner = substr($token, 1, -1);
            $inner = str_replace(['\\(', '\\)', '\\\\', '\\n', '\\r'], ['(', ')', '\\', "\n", "\r"], $inner);
            $parts[] = $inner;
        }

        return implode("\n", $parts);
    }
}
