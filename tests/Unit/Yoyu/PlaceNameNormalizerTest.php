<?php

namespace Tests\Unit\Yoyu;

use App\Domain\Yoyu\Support\PlaceNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlaceNameNormalizerTest extends TestCase
{
    #[DataProvider('normalizationCases')]
    public function test_normalizes_place_names_for_exact_match(string $input, string $expected): void
    {
        $this->assertSame($expected, PlaceNameNormalizer::normalize($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function normalizationCases(): array
    {
        return [
            'trim' => ['  ジム  ', 'ジム'],
            'collapse spaces' => ['東 京 駅', '東京駅'],
            'full-width space' => ["東京\u{3000}駅", '東京駅'],
            'case fold' => ['Tokyo Gym', 'tokyogym'],
            'full-width alnum' => ['Ｇｙｍ１', 'gym1'],
            'empty after trim' => ['   ', ''],
        ];
    }
}
