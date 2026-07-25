<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove performance chart theme tokens stay purple-family and meal gaps use null.
 */
class ChartThemeContractTest extends TestCase
{
    public function test_chart_theme_uses_purple_strength_palette_without_black(): void
    {
        $source = $this->themeSource();

        $this->assertStringContainsString("'#5B5577'", $source);
        $this->assertStringContainsString("'#81779F'", $source);
        $this->assertStringContainsString("'#AAA3BD'", $source);
        $this->assertStringContainsString("'#4D8FCB'", $source);
        $this->assertStringNotContainsString("'#000000'", $source);
        $this->assertStringNotContainsString("'black'", $source);
    }

    public function test_nutrition_series_maps_missing_days_to_null(): void
    {
        $source = $this->themeSource();

        $this->assertStringContainsString('nutritionSeriesByDate', $source);
        $this->assertStringContainsString('return point ? Number(point[key]) : null', $source);
        $this->assertStringContainsString('connectNulls: false', $source);
    }

    public function test_pfc_hex_matches_semantic_tokens(): void
    {
        $source = $this->pageSource('resources/js/lib/pfcColors.ts');

        $this->assertStringContainsString("'#3F9A70'", $source);
        $this->assertStringContainsString("'#D58A38'", $source);
        $this->assertStringContainsString("'#3B82C4'", $source);
    }

    public function test_css_defines_performance_icon_and_chart_tokens(): void
    {
        $source = $this->pageSource('resources/css/app.css');

        foreach ([
            '--cd-icon-primary',
            '--cd-icon-bg',
            '--cd-chart-primary',
            '--cd-chart-grid',
            '--cd-chart-axis',
            '--cd-protein',
            '--cd-fat',
            '--cd-carbs',
        ] as $token) {
            $this->assertStringContainsString($token, $source);
        }
    }

    private function themeSource(): string
    {
        return $this->pageSource('resources/js/lib/chartTheme.ts');
    }

    private function pageSource(string $relative): string
    {
        $absolute = base_path($relative);
        $this->assertFileExists($absolute);

        $contents = file_get_contents($absolute);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
