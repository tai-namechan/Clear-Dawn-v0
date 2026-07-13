<?php

namespace Tests\Unit\Ai;

use App\Domain\Shared\AI\AiCostCalculator;
use App\Domain\Shared\AI\AiMoney;
use Tests\TestCase;

class AiCostCalculatorTest extends TestCase
{
    public function test_reservation_uses_payload_bytes_and_max_tokens_not_their_product(): void
    {
        config([
            'ai.pricing.test-model' => ['input' => 1.0, 'output' => 5.0],
        ]);

        $calculator = app(AiCostCalculator::class);
        $payload = [
            'model' => 'test-model',
            'max_tokens' => 100,
            'messages' => [['role' => 'user', 'content' => str_repeat('あ', 100)]],
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertNotFalse($json);
        $bytes = strlen($json);

        $estimated = $calculator->estimateReservation('test-model', $payload, 100);
        $expected = AiMoney::estimateFromTokensAndRates($bytes, 100, '1', '5');

        $this->assertSame($expected->toString(), $estimated->toString());
        $this->assertNotSame(
            AiMoney::estimateFromTokensAndRates($bytes * 100, 0, '1', '5')->toString(),
            $estimated->toString(),
        );
    }

    public function test_actual_cost_is_within_conservative_estimate_for_same_payload(): void
    {
        config([
            'ai.pricing.test-model' => ['input' => 3.0, 'output' => 15.0],
        ]);

        $calculator = app(AiCostCalculator::class);
        $payload = [
            'model' => 'test-model',
            'max_tokens' => 50,
            'messages' => [['role' => 'user', 'content' => 'hello']],
        ];

        $estimated = $calculator->estimateReservation('test-model', $payload, 50);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertNotFalse($json);

        // Provider tokens cannot exceed payload bytes (input) or max_tokens (output).
        $actual = $calculator->actualCost('test-model', strlen($json), 50);

        $this->assertFalse($actual->greaterThan($estimated));
    }

    public function test_rates_for_alias_model_ids_match_configured_pricing(): void
    {
        $calculator = app(AiCostCalculator::class);

        $this->assertSame(
            ['input' => '1', 'output' => '5'],
            $calculator->ratesFor('claude-haiku-4-5'),
        );
        $this->assertSame(
            ['input' => '1', 'output' => '5'],
            $calculator->ratesFor('claude-haiku-4-5-20251001'),
        );
        $this->assertSame(
            ['input' => '2', 'output' => '10'],
            $calculator->ratesFor('claude-sonnet-5'),
        );
        $this->assertSame(
            ['input' => '3', 'output' => '15'],
            $calculator->ratesFor('claude-sonnet-4-6'),
        );
    }

    public function test_unknown_model_falls_back_to_default_pricing(): void
    {
        $calculator = app(AiCostCalculator::class);

        $this->assertSame(
            ['input' => '3', 'output' => '15'],
            $calculator->ratesFor('claude-unknown-model'),
        );
    }

    public function test_actual_cost_for_alias_models_uses_model_specific_rates_not_default(): void
    {
        $calculator = app(AiCostCalculator::class);

        // 1M input tokens at Haiku 4.5 ($1/MTok) => $1.000000
        $haiku = $calculator->actualCost('claude-haiku-4-5', 1_000_000, 0);
        $this->assertSame('1.000000', $haiku->toString());

        // Without alias pricing this would incorrectly use default 3.0/15.0 => $3.
        $default = $calculator->actualCost('claude-unknown-model', 1_000_000, 0);
        $this->assertSame('3.000000', $default->toString());
        $this->assertTrue($default->greaterThan($haiku));

        // 1M output tokens at Sonnet 5 intro ($10/MTok) => $10.000000
        $sonnet = $calculator->actualCost('claude-sonnet-5', 0, 1_000_000);
        $this->assertSame('10.000000', $sonnet->toString());
    }
}
