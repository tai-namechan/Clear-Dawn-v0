<?php

namespace Tests\Unit;

use App\Support\BarcodeNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BarcodeNormalizerTest extends TestCase
{
    private BarcodeNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new BarcodeNormalizer;
    }

    public function test_valid_ean13(): void
    {
        $result = $this->normalizer->normalize('4901234567894');

        $this->assertNotNull($result);
        $this->assertSame('4901234567894', $result['value']);
        $this->assertSame('ean13', $result['type']);
    }

    public function test_valid_ean8(): void
    {
        $result = $this->normalizer->normalize('96385074');

        $this->assertNotNull($result);
        $this->assertSame('96385074', $result['value']);
        $this->assertSame('ean8', $result['type']);
    }

    public function test_valid_upca_normalized_to_ean13(): void
    {
        // UPC-A 036000291452 → EAN-13 0036000291452
        $result = $this->normalizer->normalize('036000291452');

        $this->assertNotNull($result);
        $this->assertSame('0036000291452', $result['value']);
        $this->assertSame('upca', $result['type']);
    }

    public function test_strips_whitespace_and_hyphens(): void
    {
        $result = $this->normalizer->normalize(' 4901-2345-67894 ');

        $this->assertNotNull($result);
        $this->assertSame('4901234567894', $result['value']);
    }

    public function test_invalid_check_digit_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('4901234567890'));
    }

    public function test_wrong_length_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('12345'));
        $this->assertNull($this->normalizer->normalize('1234567890'));
        $this->assertNull($this->normalizer->normalize('12345678901234'));
    }

    public function test_non_numeric_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize('abcdefgh'));
        $this->assertNull($this->normalizer->normalize('490123456789x'));
    }

    public function test_empty_string_returns_null(): void
    {
        $this->assertNull($this->normalizer->normalize(''));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function realWorldBarcodeProvider(): array
    {
        return [
            'Coca-Cola Japan (EAN-13)' => ['4902102112154', 'ean13'],
            'Japanese snack (EAN-13)' => ['4903015100214', 'ean13'],
            'EAN-8 example' => ['55123457', 'ean8'],
        ];
    }

    #[DataProvider('realWorldBarcodeProvider')]
    public function test_real_world_barcodes(string $barcode, string $expectedType): void
    {
        $result = $this->normalizer->normalize($barcode);

        $this->assertNotNull($result, "Barcode {$barcode} should be valid");
        $this->assertSame($expectedType, $result['type']);
    }
}
