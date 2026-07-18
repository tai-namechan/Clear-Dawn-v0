<?php

namespace Tests\Unit\Yoyu\Money;

use App\Domain\Yoyu\Money\Support\MoneyCsvNormalizer;
use InvalidArgumentException;
use Tests\TestCase;

class MoneyCsvNormalizerTest extends TestCase
{
    private MoneyCsvNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new MoneyCsvNormalizer;
    }

    public function test_parse_date_with_format(): void
    {
        $date = $this->normalizer->parseDate('2026/07/15', ['date_format' => 'Y/m/d']);

        $this->assertSame('2026-07-15', $date);
    }

    public function test_parse_date_without_format_accepts_known_formats_only(): void
    {
        $this->assertSame('2026-07-15', $this->normalizer->parseDate('2026-07-15', []));
        $this->assertSame('2026-07-05', $this->normalizer->parseDate('2026/7/5', []));
        $this->assertSame('2026-07-15', $this->normalizer->parseDate('20260715', []));
        $this->assertSame('2026-07-15', $this->normalizer->parseDate('2026年7月15日', []));
    }

    public function test_parse_date_without_format_rejects_ambiguous_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->normalizer->parseDate('01/02/03', []);
    }

    public function test_parse_amount_strips_currency_and_commas(): void
    {
        $this->assertSame(12345, $this->normalizer->parseAmountToMinor('¥12,345'));
        $this->assertSame(-500, $this->normalizer->parseAmountToMinor('-500'));
    }

    public function test_resolve_amount_expense_positive(): void
    {
        $result = $this->normalizer->resolveAmount(
            ['金額' => '1200'],
            [
                'amount_column' => '金額',
                'amount_sign' => 'expense_positive',
            ],
        );

        $this->assertSame(1200, $result['amount_minor']);
        $this->assertSame('outflow', $result['direction']);
    }

    public function test_resolve_amount_negative_expense_positive_is_inflow(): void
    {
        $result = $this->normalizer->resolveAmount(
            ['金額' => '-800'],
            [
                'amount_column' => '金額',
                'amount_sign' => 'expense_positive',
            ],
        );

        $this->assertSame(800, $result['amount_minor']);
        $this->assertSame('inflow', $result['direction']);
    }

    public function test_resolve_amount_debit_credit_columns(): void
    {
        $outflow = $this->normalizer->resolveAmount(
            ['出金' => '3000', '入金' => ''],
            [
                'debit_column' => '出金',
                'credit_column' => '入金',
            ],
        );
        $this->assertSame(3000, $outflow['amount_minor']);
        $this->assertSame('outflow', $outflow['direction']);

        $inflow = $this->normalizer->resolveAmount(
            ['出金' => '', '入金' => '5000'],
            [
                'debit_column' => '出金',
                'credit_column' => '入金',
            ],
        );
        $this->assertSame(5000, $inflow['amount_minor']);
        $this->assertSame('inflow', $inflow['direction']);
    }

    public function test_normalize_description_collapses_whitespace(): void
    {
        $this->assertSame(
            'コンビニ a店',
            $this->normalizer->normalizeDescription("  コンビニ   A店 \n"),
        );
    }

    public function test_row_hash_is_stable(): void
    {
        $a = $this->normalizer->rowHash('acct1', '2026-07-01', 1000, 'shop');
        $b = $this->normalizer->rowHash('acct1', '2026-07-01', 1000, 'shop');
        $c = $this->normalizer->rowHash('acct1', '2026-07-01', 1001, 'shop');

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertSame(64, strlen($a));
    }

    public function test_invalid_amount_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->normalizer->parseAmountToMinor('abc');
    }
}
