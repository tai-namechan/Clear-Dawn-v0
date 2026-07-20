<?php

namespace App\Support;

/**
 * 食品バーコード（EAN-8 / UPC-A / EAN-13）の検証と正規化（設計 §13.3 手順2）。
 *
 * - 桁数と GS1 チェックディジットを検証する
 * - UPC-A（12桁）は先頭 0 埋めで EAN-13 に正規化する（GS1 の重み付けは
 *   右端基準のため、先頭 0 を足してもチェックディジットは変わらない）
 */
final class BarcodeNormalizer
{
    /**
     * @return array{value: string, type: string}|null 不正なら null
     */
    public function normalize(string $raw): ?array
    {
        $digits = preg_replace('/[\s-]/', '', trim($raw)) ?? '';

        if (! preg_match('/\A\d{8}\z|\A\d{12}\z|\A\d{13}\z/', $digits)) {
            return null;
        }

        if (! $this->hasValidCheckDigit($digits)) {
            return null;
        }

        return match (strlen($digits)) {
            8 => ['value' => $digits, 'type' => 'ean8'],
            12 => ['value' => '0'.$digits, 'type' => 'upca'],
            13 => ['value' => $digits, 'type' => 'ean13'],
            default => null,
        };
    }

    /**
     * GS1 標準チェックディジット: 右端（チェックディジット除く）から
     * 3,1,3,1… の重みで合計し、(10 - 合計 % 10) % 10 が末尾と一致すること。
     */
    private function hasValidCheckDigit(string $digits): bool
    {
        $check = (int) substr($digits, -1);
        $body = substr($digits, 0, -1);

        $sum = 0;
        $weight = 3;

        foreach (array_reverse(str_split($body)) as $digit) {
            $sum += ((int) $digit) * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - ($sum % 10)) % 10 === $check;
    }
}
