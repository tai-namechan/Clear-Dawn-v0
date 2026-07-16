<?php

namespace Tests\Unit\Kioku;

use App\Domain\Kioku\Services\KiokuTagNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KiokuTagNormalizerTest extends TestCase
{
    private KiokuTagNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new KiokuTagNormalizer;
    }

    public function test_trims_halfwidth_and_fullwidth_whitespace(): void
    {
        $this->assertSame(
            ['ヨガ', '仕事'],
            $this->normalizer->normalize(['  ヨガ  ', '　仕事　']),
        );
    }

    public function test_collapses_consecutive_whitespace_to_single_halfwidth_space(): void
    {
        $this->assertSame(
            ['朝 ヨガ'],
            $this->normalizer->normalize(['朝　 　ヨガ']),
        );
    }

    public function test_strips_leading_hash_markers(): void
    {
        $this->assertSame(
            ['ヨガ', '仕事', 'nested'],
            $this->normalizer->normalize(['#ヨガ', '＃仕事', '##nested']),
        );
    }

    public function test_drops_empty_and_non_string_values(): void
    {
        $this->assertSame(
            ['残る'],
            $this->normalizer->normalize(['', '   ', '　　', null, 12, ['nested'], '残る']),
        );
    }

    public function test_case_insensitive_dedupe_keeps_first_display_form(): void
    {
        $this->assertSame(
            ['Vite', '仕事'],
            $this->normalizer->normalize(['Vite', 'vite', 'VITE', '仕事', '仕事']),
        );
    }

    public function test_allows_exactly_forty_chars_and_drops_forty_one(): void
    {
        $allowed = str_repeat('あ', KiokuTagNormalizer::MAX_TAG_CHARS);
        $tooLong = str_repeat('あ', KiokuTagNormalizer::MAX_TAG_CHARS + 1);

        $this->assertSame(
            [$allowed],
            $this->normalizer->normalize([$allowed, $tooLong]),
        );
    }

    public function test_caps_at_eight_tags_preserving_stable_input_order(): void
    {
        $input = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'];

        $this->assertSame(
            ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'],
            $this->normalizer->normalize($input),
        );
    }

    public function test_is_pure_and_does_not_mutate_input_array(): void
    {
        $input = ['#ヨガ', ' 仕事 '];
        $copy = $input;

        $this->normalizer->normalize($input);

        $this->assertSame($copy, $input);
    }

    #[DataProvider('cleanupProvider')]
    public function test_cleanup_combinations(array $input, array $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }

    /**
     * @return array<string, array{0: array<mixed>, 1: list<string>}>
     */
    public static function cleanupProvider(): array
    {
        return [
            'hash then spaces' => [['#  ヨガ  '], ['ヨガ']],
            'fullwidth hash' => [['＃朝ルーティン'], ['朝ルーティン']],
            'only hashes' => [['###', '＃＃'], []],
        ];
    }
}
