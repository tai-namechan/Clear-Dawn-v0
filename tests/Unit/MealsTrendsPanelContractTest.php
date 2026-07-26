<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove meal trends date filter stays usable on narrow viewports.
 */
class MealsTrendsPanelContractTest extends TestCase
{
    public function test_date_filter_uses_two_column_range_on_mobile(): void
    {
        $source = $this->componentSource(
            'resources/js/components/meals/MealsTrendsPanel.vue',
        );

        $this->assertStringContainsString(
            'grid grid-cols-2 items-end gap-2 sm:gap-3',
            $source,
            'Start/end dates sit in two equal columns like common mobile date-range forms',
        );
        $this->assertSame(
            2,
            substr_count($source, 'flex min-w-0 flex-col gap-1'),
            'Start/end fields must shrink below native date input intrinsic width',
        );
        $this->assertStringContainsString(
            'w-full min-w-0',
            $source,
            'Native date inputs must fill their column without overflowing',
        );
        $this->assertStringContainsString(
            'w-full font-sans sm:w-auto sm:self-end',
            $source,
            'Apply button spans full width under the date pair on mobile',
        );
        $this->assertStringNotContainsString(
            'flex flex-wrap items-end gap-3',
            $source,
            'flex-wrap date filters overflow on phone-width viewports',
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
