<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Prove OS / Clear Dawn sidebar spacing contracts that prevent
 * background-art text overlap and header cramping regressions.
 */
class SidebarSpacingContractTest extends TestCase
{
    public function test_os_sidebar_reserves_space_above_nav_and_keeps_scroll(): void
    {
        $source = $this->componentSource('resources/js/components/os/OsSidebar.vue');

        $this->assertStringContainsString(
            'min-h-48',
            $source,
            'OS sidebar header must reserve brand/tagline space in the background art',
        );
        $this->assertStringContainsString(
            'mt-14',
            $source,
            'OS sidebar nav must sit below the brand band without using the pre-regression mt-8',
        );
        $this->assertStringNotContainsString(
            'mt-8 flex flex-col items-center gap-3',
            $source,
            'Regression guard: mt-8 re-collides with baked-in sidebar art text',
        );
        $this->assertStringContainsString(
            'overflow-y-auto',
            $source,
            'OS sidebar content must remain scrollable so the last nav item stays reachable',
        );
        $this->assertStringContainsString(
            'min-h-12',
            $source,
            'OS sidebar footer should stay compact to avoid pushing お金 off-screen',
        );
    }

    public function test_clear_dawn_header_keeps_breathing_room_beside_title(): void
    {
        $source = $this->componentSource('resources/js/components/AppSidebarHeader.vue');

        $this->assertStringContainsString(
            'flex min-w-0 flex-1 items-center gap-3 md:gap-4',
            $source,
            'Clear Dawn header trigger/title/switcher need relaxed horizontal gaps',
        );
        $this->assertStringNotContainsString(
            'flex min-w-0 flex-1 items-center gap-2 md:gap-3',
            $source,
            'Regression guard: tighter left-header gaps make Clear Dawn feel cramped',
        );
        $this->assertStringNotContainsString(
            'SidebarTrigger class="-ml-1',
            $source,
            'Negative trigger margin pulls Clear Dawn title too close to the toggle',
        );
    }

    #[DataProvider('requiredComponentPaths')]
    public function test_spacing_contract_targets_exist(string $relativePath): void
    {
        $absolute = base_path($relativePath);

        $this->assertFileExists($absolute);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function requiredComponentPaths(): array
    {
        return [
            'os sidebar' => ['resources/js/components/os/OsSidebar.vue'],
            'clear dawn header' => ['resources/js/components/AppSidebarHeader.vue'],
        ];
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
