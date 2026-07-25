<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove routine items index lays out item cards four-across on large screens.
 */
class RoutineItemsIndexLayoutContractTest extends TestCase
{
    public function test_routine_items_index_uses_four_column_card_grid(): void
    {
        $source = $this->componentSource('resources/js/pages/RoutineItems/Index.vue');

        $this->assertStringContainsString(
            'max-w-7xl',
            $source,
            'Four-column cards need a wider page shell than the former max-w-3xl list',
        );
        $this->assertStringContainsString(
            'grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4',
            $source,
            'Items must render as a responsive card grid ending at four columns',
        );
        $this->assertStringContainsString(
            'rounded-xl border border-cd-line/80 bg-white/50 p-4',
            $source,
            'Each routine item must be a card, not a full-width list row',
        );
        $this->assertStringNotContainsString(
            'ul class="flex flex-col"',
            $source,
            'Regression guard: vertical list layout for items is retired',
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
