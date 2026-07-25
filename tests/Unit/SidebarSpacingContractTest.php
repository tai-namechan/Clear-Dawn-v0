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
        $this->assertStringContainsString(
            'cd-app-header',
            $source,
            'Clear Dawn header must use the shared chrome class for warm white fill',
        );
        $this->assertStringContainsString(
            'cd-app-header-title',
            $source,
            'Clear Dawn title uses neutral ink, not brand purple fill',
        );
        $this->assertStringNotContainsString(
            'max-md:bg-primary',
            $source,
            'Mobile header no longer uses solid primary purple chrome',
        );
        $this->assertStringNotContainsString(
            'max-md:text-primary-foreground',
            $source,
            'Mobile Clear Dawn header title/trigger stay on warm white chrome',
        );
    }

    public function test_clear_dawn_sidebar_keeps_top_breathing_room(): void
    {
        $source = $this->componentSource('resources/js/components/AppSidebar.vue');

        $this->assertStringContainsString(
            'cd-sidebar-nav-link',
            $source,
            'Nav links use shared active/hover chrome classes',
        );
        $this->assertStringContainsString(
            'cd-sidebar-decor',
            $source,
            'Sidebar decorations stay but are toned via shared opacity',
        );
        $this->assertStringContainsString(
            'mx-auto mt-8 flex items-baseline',
            $source,
            'CD logo needs top margin so the brand is not flush to the viewport edge',
        );
        $this->assertStringContainsString(
            'mt-3 font-serif text-[0.7rem]',
            $source,
            'Clear Dawn wordmark needs space under the CD monogram',
        );
        $this->assertStringContainsString(
            'justify-center overflow-visible landscape-compact:justify-start',
            $source,
            'Tall desktops should vertically center the nav block instead of packing it under the logo',
        );
        $this->assertStringContainsString(
            'gap-5 group-data-[collapsible=icon]:mt-8',
            $source,
            'Desktop nav item gaps need room so the cluster does not look crushed',
        );
        $this->assertStringContainsString(
            'landscape-compact:mt-10 landscape-compact:gap-2',
            $source,
            'iPad landscape compact mode must keep usable brand-to-nav breathing room',
        );
        $this->assertStringNotContainsString(
            'cd-mask-violin',
            $source,
            'Sidebar violin decoration is retired',
        );
        $this->assertStringNotContainsString(
            'mt-24 flex flex-col items-center gap-3',
            $source,
            'Regression guard: previous desktop top pack felt cramped on tall laptops',
        );
        $this->assertStringNotContainsString(
            'landscape-compact:mt-6 landscape-compact:gap-1.5',
            $source,
            'Regression guard: previous iPad compact packing crushed the top of the sidebar',
        );
    }

    public function test_clear_dawn_sidebar_css_covers_teleported_mobile_sheet(): void
    {
        $source = $this->componentSource('resources/css/app.css');

        $this->assertStringContainsString(
            "[data-sidebar='sidebar'][data-mobile='true']:has(.cd-sidebar-panel)",
            $source,
            'CD sidebar chrome must target the teleported mobile SheetContent',
        );
        $this->assertStringContainsString(
            '--cd-sidebar-start:',
            $source,
            'Sidebar night tokens must be defined',
        );
        $this->assertStringContainsString(
            'var(--cd-sidebar-end) 100%',
            $source,
            'Sidebar gradient must end on deep plum, not pale lavender',
        );
        $this->assertStringNotContainsString(
            'var(--cd-lavender-mist) 100%',
            $source,
            'Regression guard: pale lavender sidebar foot is retired',
        );
        $this->assertStringContainsString(
            '.cd-app-header',
            $source,
            'Header warm-white chrome must live in shared CSS',
        );
        $this->assertStringContainsString(
            'background-color: #ffffff',
            $source,
            'Shared cd-panel cards use opaque white',
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
            'clear dawn sidebar' => ['resources/js/components/AppSidebar.vue'],
            'clear dawn sidebar css' => ['resources/css/app.css'],
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
