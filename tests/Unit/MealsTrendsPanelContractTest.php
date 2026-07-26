<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove meal trends date filter stays usable on narrow viewports.
 */
class MealsTrendsPanelContractTest extends TestCase
{
    public function test_date_filter_uses_shrinkable_grid_on_mobile(): void
    {
        $source = $this->componentSource(
            'resources/js/components/meals/MealsTrendsPanel.vue',
        );

        $this->assertStringContainsString(
            'grid w-full grid-cols-2 items-end gap-3 sm:flex sm:w-auto sm:flex-nowrap sm:items-end',
            $source,
            'Date inputs must share two equal columns on mobile, then sit in a single row from sm up',
        );
        $this->assertSame(
            2,
            substr_count($source, 'flex min-w-0 flex-col gap-1 sm:w-40'),
            'Start/end date fields must allow shrinking below native date input intrinsic width',
        );
        $this->assertStringContainsString(
            'col-span-2 w-full font-sans sm:w-auto',
            $source,
            'Apply button must span full width under the dates on mobile',
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
