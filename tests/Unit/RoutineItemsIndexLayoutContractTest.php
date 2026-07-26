<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove category cards sit four-across; headers and item names use vivid colors.
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
        $this->assertStringContainsString(
            'categoryHeaderClasses(group.category)',
            $source,
            'Category headers (筋力 / 野球 …) must take a solid category color',
        );
        $this->assertStringContainsString(
            'categoryNameClasses(group.category)',
            $source,
            'Item names must use a different vivid chip color from the header',
        );
        $this->assertStringNotContainsString(
            'bg-primary/8',
            $source,
            'Subtle primary tint alone is not enough for the requested flashy headers/names',
        );
    }

    public function test_category_color_map_separates_header_and_name(): void
    {
        $source = $this->componentSource(
            'resources/js/lib/routineItemCategoryColors.ts',
        );

        foreach ([
            'strength',
            'baseball',
            'mobility',
            'care',
            'music',
            'study',
            'life',
            'other',
        ] as $category) {
            $this->assertMatchesRegularExpression(
                '/'.$category.':\s*\{\s*header:\s*\'[^\']+\'\s*,\s*name:\s*\'[^\']+\'/',
                $source,
                "Category {$category} needs distinct header and name color classes",
            );
        }

        $this->assertStringContainsString(
            'bg-rose-50 text-rose-800',
            $source,
            'Strength header should be a soft tint fill, not a loud solid',
        );
        $this->assertStringContainsString(
            'bg-orange-50 text-orange-700',
            $source,
            'Strength item names should use a soft different chip than the header',
        );
        $this->assertStringNotContainsString(
            'bg-rose-600 text-white',
            $source,
            'Regression: flashy solid header fills are too loud for Clear Dawn',
        );
        $this->assertStringNotContainsString(
            'bg-orange-400 text-orange-950',
            $source,
            'Regression: flashy solid name chips are too loud for Clear Dawn',
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
