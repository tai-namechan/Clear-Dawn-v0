<?php

namespace Tests\Unit\Yoyu;

use App\Domain\Yoyu\Services\MarginAnalyzer;
use Tests\TestCase;

class MarginAnalyzerTest extends TestCase
{
    private MarginAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new MarginAnalyzer;
    }

    public function test_empty_day_is_score_100_yuttari(): void
    {
        $result = $this->analyzer->analyze(0, 0);

        $this->assertSame(100, $result->marginScore);
        $this->assertSame('ゆったり', $result->marginLabel);
    }

    public function test_boundary_score_50_is_chodo(): void
    {
        // ratio 0.5 → score 50 → ちょうどいい (20 <= score <= 50)
        // 1 - load/960 = 0.5 → load = 480
        $result = $this->analyzer->analyze(480, 0);
        $this->assertSame(50, $result->marginScore);
        $this->assertSame('ちょうどいい', $result->marginLabel);
    }

    public function test_boundary_score_20_is_chodo(): void
    {
        // 1 - load/960 = 0.2 → load = 768
        $result = $this->analyzer->analyze(768, 0);
        $this->assertSame(20, $result->marginScore);
        $this->assertSame('ちょうどいい', $result->marginLabel);
    }

    public function test_score_19_is_tsumari(): void
    {
        // score 19 → load ≈ 778
        $result = $this->analyzer->analyze(778, 0);
        $this->assertSame(19, $result->marginScore);
        $this->assertSame('詰まり気味', $result->marginLabel);
    }

    public function test_score_51_is_yuttari(): void
    {
        // score 51 → load ≈ 470
        $result = $this->analyzer->analyze(470, 0);
        $this->assertSame(51, $result->marginScore);
        $this->assertSame('ゆったり', $result->marginLabel);
    }

    public function test_task_minutes_are_capped_at_240(): void
    {
        $result = $this->analyzer->analyze(0, 500);
        $this->assertSame(240, $result->taskMinutes);
        // load 240 → ratio 1 - 240/960 = 0.75 → 75
        $this->assertSame(75, $result->marginScore);
    }

    public function test_score_never_below_zero_or_above_100(): void
    {
        $over = $this->analyzer->analyze(2000, 500);
        $this->assertSame(0, $over->marginScore);
        $this->assertSame(960, $over->loadMinutes);

        $empty = $this->analyzer->analyze(0, 0);
        $this->assertSame(100, $empty->marginScore);
    }

    public function test_busy_and_tasks_add_without_inventing_overlap(): void
    {
        // Caller must pass already-merged busy minutes.
        $result = $this->analyzer->analyze(120, 90);
        $this->assertSame(210, $result->loadMinutes);
    }
}
