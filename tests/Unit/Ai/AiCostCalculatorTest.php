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
}
