<?php

namespace Tests\Unit\Yoyu\Money;

use App\Domain\Yoyu\Money\Support\LoanInterestEstimator;
use Tests\TestCase;

class LoanInterestEstimatorTest extends TestCase
{
    private LoanInterestEstimator $estimator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->estimator = new LoanInterestEstimator;
    }

    public function test_zero_principal_returns_zero_months(): void
    {
        $result = $this->estimator->estimate(0, 1500, 10_000);

        $this->assertSame(['months' => 0, 'total_interest_minor' => 0], $result);
    }

    public function test_insufficient_payment_returns_null(): void
    {
        // 1_000_000 principal at 18% APR → monthly interest ≈ 15_000; payment 10_000 is too low.
        $result = $this->estimator->estimate(1_000_000, 1800, 10_000);

        $this->assertNull($result);
    }

    public function test_zero_rate_payoff_is_ceil_division(): void
    {
        $result = $this->estimator->estimate(100_000, 0, 25_000);

        $this->assertNotNull($result);
        $this->assertSame(4, $result['months']);
        $this->assertSame(0, $result['total_interest_minor']);
    }

    public function test_positive_rate_accumulates_interest(): void
    {
        $result = $this->estimator->estimate(100_000, 1200, 10_000);

        $this->assertNotNull($result);
        $this->assertGreaterThan(0, $result['total_interest_minor']);
        $this->assertGreaterThan(10, $result['months']);
    }

    public function test_prepay_preview_reduces_months_and_interest(): void
    {
        $preview = $this->estimator->prepayPreview(500_000, 1200, 20_000, 100_000);

        $this->assertNotNull($preview['baseline_months']);
        $this->assertNotNull($preview['with_prepay_months']);
        $this->assertLessThanOrEqual(
            (int) $preview['baseline_months'],
            (int) $preview['with_prepay_months'],
        );
        $this->assertGreaterThanOrEqual(0, (int) $preview['months_saved']);
        $this->assertGreaterThanOrEqual(0, (int) $preview['interest_saved_minor']);
    }
}
