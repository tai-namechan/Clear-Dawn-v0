<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove Records index mini-charts keep legends off the plot so
 * weight/sleep/strength series labels stay readable on narrow cards.
 */
class RecordsIndexChartContractTest extends TestCase
{
    public function test_condition_and_strength_mini_charts_keep_legends_at_bottom(): void
    {
        $source = $this->componentSource('resources/js/pages/Records/Index.vue');

        $this->assertStringContainsString(
            "legend: chartLegend(10, { bottom: 0, left: 'center' })",
            $source,
            'Condition and strength mini-charts must pin legends to the bottom',
        );
        $this->assertStringNotContainsString(
            "name: '体重 kg'",
            $source,
            'Axis names crowd the compact condition chart; units belong in tooltip/legend',
        );
        $this->assertStringNotContainsString(
            "name: '睡眠 時間'",
            $source,
            'Axis names crowd the compact condition chart; units belong in tooltip/legend',
        );
        $this->assertSame(
            2,
            substr_count($source, "chartLegend(10, { bottom: 0, left: 'center' })"),
            'Both condition and strength mini-charts should use a bottom-centered legend',
        );
    }

    private function componentSource(string $relativePath): string
    {
        $absolute = base_path($relativePath);
        $this->assertFileExists($absolute);

        $contents = file_get_contents($absolute);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
