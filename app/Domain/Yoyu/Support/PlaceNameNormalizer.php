<?php

namespace App\Domain\Yoyu\Support;

/**
 * Normalize place names for exact-match travel lookup (no partial match).
 */
final class PlaceNameNormalizer
{
    public static function normalize(string $name): string
    {
        $normalized = trim($name);
        $normalized = mb_convert_kana($normalized, 'as', 'UTF-8');
        $normalized = mb_strtolower($normalized, 'UTF-8');
        $normalized = preg_replace('/\s+/u', '', $normalized) ?? '';

        return $normalized;
    }
}
