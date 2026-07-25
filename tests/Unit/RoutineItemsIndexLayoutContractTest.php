<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove category cards sit four-across; items inside each card stay vertical.
 */
class RoutineItemsIndexLayoutContractTest extends TestCase
{
    public function test_category_cards_are_four_across_with_vertical_items(): void
    {
        $source = $this->componentSource('resources/js/pages/RoutineItems/Index.vue');

        $this->assertStringContainsString(
            'max-w-7xl',
            $source,
            'Four category cards need a wider page shell than the former max-w-3xl stack',
        );
        $this->assertStringContainsString(
            'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4',
            $source,
            'Category cards must lay out in a responsive grid ending at four columns',
        );
        $this->assertStringContainsString(
            'ul class="flex flex-col"',
            $source,
            'Items inside each category card must remain a vertical list',
        );
        $this->assertStringNotContainsString(
            'lg:grid-cols-4 gap-3 p-4',
            $source,
            'Regression guard: items themselves must not use the four-column grid',
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
