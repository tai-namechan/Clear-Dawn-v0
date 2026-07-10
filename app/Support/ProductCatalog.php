<?php

namespace App\Support;

/**
 * Seed K Personal OS のプロダクト定義（切替 UI・共有 props の単一情報源）。
 */
final class ProductCatalog
{
    public const CLEAR_DAWN = 'clear_dawn';

    public const YOYU = 'yoyu';

    public const KIOKU = 'kioku';

    /**
     * @return list<array{key: string, name: string, tagline: string, href: string}>
     */
    public static function all(): array
    {
        return [
            [
                'key' => self::CLEAR_DAWN,
                'name' => 'Clear Dawn',
                'tagline' => '思考の整理・人生の方針',
                'href' => route('dashboard'),
            ],
            [
                'key' => self::YOYU,
                'name' => 'ヨユウ',
                'tagline' => '焦らず、前へ回す秘書',
                'href' => route('yoyu.home'),
            ],
            [
                'key' => self::KIOKU,
                'name' => 'キオク',
                'tagline' => '記憶の保存・検索・想起',
                'href' => route('kioku.home'),
            ],
        ];
    }

    public static function resolveFromPath(string $path): string
    {
        if (str_starts_with($path, 'yoyu')) {
            return self::YOYU;
        }

        if (str_starts_with($path, 'kioku')) {
            return self::KIOKU;
        }

        return self::CLEAR_DAWN;
    }
}
