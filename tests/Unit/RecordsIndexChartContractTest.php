<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove Records index mini-charts share one plot box and keep legends /
 * axis names off the plot so the trend and strength cards stay aligned.
 */
class RecordsIndexChartContractTest extends TestCase
{
    public function test_records_mini_charts_share_grid_and_keep_labels_clear(): void
    {
        $source = $this->componentSource('resources/js/pages/Records/Index.vue');

        $this->assertStringContainsString(
            'const RECORDS_MINI_GRID',
            $source,
            'Trend and strength charts must share one grid constant for alignment',
        );
        $this->assertSame(
            2,
            substr_count($source, 'grid: { ...RECORDS_MINI_GRID'),
            'The trend-card builder and the strength chart should both use RECORDS_MINI_GRID',
        );
        $this->assertSame(
            1,
            substr_count($source, ':option="trend.option"'),
            'Sleep / weight / fatigue trends render from one card template so they share a height',
        );
        $this->assertSame(
            1,
            substr_count($source, 'class="mt-2 !h-32"'),
            'All trend cards should share the same chart height',
        );
        $this->assertSame(
            1,
            substr_count($source, "chartLegend(10, { bottom: 0, left: 'center' })"),
            'The strength legend must sit at the bottom, off the plot',
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
        $this->assertStringNotContainsString(
            "name: 'kg'",
            $source,
            'Strength mini-chart must not place a kg axis name over tick labels',
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
