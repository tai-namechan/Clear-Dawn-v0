<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove Records index mini-charts share one plot box and keep legends /
 * axis names off the plot so meal and condition cards stay aligned.
 */
class RecordsIndexChartContractTest extends TestCase
{
    public function test_records_mini_charts_share_grid_and_keep_labels_clear(): void
    {
        $source = $this->componentSource('resources/js/pages/Records/Index.vue');

        $this->assertStringContainsString(
            'const RECORDS_MINI_GRID',
            $source,
            'Meal and condition mini-charts must share one grid constant for alignment',
        );
        $this->assertSame(
            4,
            substr_count($source, 'grid: { ...RECORDS_MINI_GRID }'),
            'All four Records mini-charts should use RECORDS_MINI_GRID',
        );
        $this->assertSame(
            4,
            substr_count($source, 'class="!h-40"'),
            'All four Records mini-charts should share the same height',
        );
        $this->assertSame(
            3,
            substr_count($source, "chartLegend(10, { bottom: 0, left: 'center' })"),
            'PFC / condition / strength legends must sit at the bottom',
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
