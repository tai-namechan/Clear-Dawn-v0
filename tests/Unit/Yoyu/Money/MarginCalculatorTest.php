<?php

namespace Tests\Unit\Yoyu\Money;

use App\Domain\Yoyu\Money\Services\MarginCalculator;
use Tests\TestCase;

class MarginCalculatorTest extends TestCase
{
    private MarginCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new MarginCalculator;
    }

    public function test_ac01_projected_cash_and_safe_to_spend(): void
    {
        $result = $this->calculator->calculate(
            fundsMinor: 100_000,
            cashflows: [
                [
                    'direction' => 'inflow',
                    'amount_minor' => 50_000,
                    'certainty' => 'confirmed',
                    'status' => 'planned',
                    'due_on' => '2026-07-15',
                ],
                [
                    'direction' => 'outflow',
                    'amount_minor' => 80_000,
                    'certainty' => 'confirmed',
                    'status' => 'confirmed',
                    'due_on' => '2026-07-20',
                ],
            ],
            uncertainReserveBps: 10_000,
            minimumLivingBudgetMinor: 20_000,
            safetyBufferMinor: 10_000,
            essentialConsumedMinor: 0,
            essentialScheduledMinor: 0,
            asOf: '2026-07-01',
            horizonEnd: '2026-07-31',
        );

        $this->assertSame('100000', $result->fundsMinor);
        $this->assertSame('50000', $result->confirmedIncomeMinor);
        $this->assertSame('80000', $result->confirmedOutflowMinor);
        $this->assertSame('70000', $result->projectedCashMinor);
        $this->assertSame('40000', $result->projectedMarginMinor);
        $this->assertSame('40000', $result->safeToSpendMinor);
        $this->assertSame('0', $result->shortfallMinor);
        $this->assertTrue($result->isComplete);
        $this->assertSame([], $result->missingSettings);
    }

    public function test_ac02_shortfall_when_outflows_exceed_margin(): void
    {
        $result = $this->calculator->calculate(
            fundsMinor: 100_000,
            cashflows: [
                [
                    'direction' => 'inflow',
                    'amount_minor' => 50_000,
                    'certainty' => 'confirmed',
                    'status' => 'planned',
                    'due_on' => '2026-07-15',
                ],
                [
                    'direction' => 'outflow',
                    'amount_minor' => 130_000,
                    'certainty' => 'confirmed',
                    'status' => 'confirmed',
                    'due_on' => '2026-07-20',
                ],
            ],
            uncertainReserveBps: 10_000,
            minimumLivingBudgetMinor: 20_000,
            safetyBufferMinor: 10_000,
            essentialConsumedMinor: 0,
            essentialScheduledMinor: 0,
            asOf: '2026-07-01',
            horizonEnd: '2026-07-31',
        );

        // F+Ic-Oc = 100000+50000-130000 = 20000; margin = 20000-20000-10000 = -10000
        $this->assertSame('20000', $result->projectedCashMinor);
        $this->assertSame('-10000', $result->projectedMarginMinor);
        $this->assertSame('0', $result->safeToSpendMinor);
        $this->assertSame('10000', $result->shortfallMinor);
    }

    public function test_settings_incomplete_when_living_budget_null(): void
    {
        $result = $this->calculator->calculate(
            fundsMinor: 100_000,
            cashflows: [],
            uncertainReserveBps: 10_000,
            minimumLivingBudgetMinor: null,
            safetyBufferMinor: 10_000,
            essentialConsumedMinor: 0,
            essentialScheduledMinor: 0,
            asOf: '2026-07-01',
            horizonEnd: '2026-07-31',
        );

        $this->assertFalse($result->isComplete);
        $this->assertContains('minimum_living_budget_minor', $result->missingSettings);
        $this->assertSame('0', $result->livingReserveMinor);
    }

    public function test_excludes_settled_canceled_and_deferred(): void
    {
        $result = $this->calculator->calculate(
            fundsMinor: 100_000,
            cashflows: [
                [
                    'direction' => 'outflow',
                    'amount_minor' => 40_000,
                    'certainty' => 'confirmed',
                    'status' => 'settled',
                    'due_on' => '2026-07-10',
                ],
                [
                    'direction' => 'outflow',
                    'amount_minor' => 30_000,
                    'certainty' => 'confirmed',
                    'status' => 'canceled',
                    'due_on' => '2026-07-11',
                ],
                [
                    'direction' => 'outflow',
                    'amount_minor' => 20_000,
                    'certainty' => 'confirmed',
                    'status' => 'deferred',
                    'due_on' => '2026-07-12',
                ],
                [
                    'direction' => 'outflow',
                    'amount_minor' => 10_000,
                    'certainty' => 'confirmed',
                    'status' => 'planned',
                    'due_on' => '2026-07-13',
                ],
            ],
            uncertainReserveBps: 10_000,
            minimumLivingBudgetMinor: 0,
            safetyBufferMinor: 0,
            essentialConsumedMinor: 0,
            essentialScheduledMinor: 0,
            asOf: '2026-07-01',
            horizonEnd: '2026-07-31',
        );

        $this->assertSame('10000', $result->confirmedOutflowMinor);
        $this->assertSame('90000', $result->projectedCashMinor);
    }
}
