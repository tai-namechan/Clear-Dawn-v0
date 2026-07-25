<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove RoutinesHubTabs keeps URL-based hub navigation while using
 * underline-tab visuals (same family as PageViewTabs).
 */
class RoutinesHubTabsContractTest extends TestCase
{
    public function test_hub_tabs_keep_existing_urls(): void
    {
        $source = $this->componentSource();

        foreach (['/programs', '/routines', '/today', '/history'] as $href) {
            $this->assertStringContainsString(
                "href: '{$href}'",
                $source,
                "Hub tab must keep navigation URL {$href}",
            );
        }
    }

    public function test_hub_tabs_use_underline_style_not_pill_buttons(): void
    {
        $source = $this->componentSource();

        $this->assertStringContainsString(
            'border-b border-cd-line',
            $source,
            'Hub tabs must use underline tab bar (PageViewTabs family)',
        );
        $this->assertStringContainsString(
            'h-0.5 rounded-full bg-primary',
            $source,
            'Active hub tab must show primary underline indicator',
        );
        $this->assertStringNotContainsString(
            'rounded-full border px-4 py-1.5',
            $source,
            'Regression guard: pill-button hub chips wrap poorly on mobile',
        );
    }

    public function test_today_session_header_stacks_on_mobile(): void
    {
        $source = file_get_contents(base_path('resources/js/pages/Today/Index.vue'));
        $this->assertNotFalse($source);

        $this->assertStringContainsString(
            'flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between',
            $source,
            'Today session title and add button must stack on narrow screens',
        );
        $this->assertStringContainsString(
            'w-full shrink-0 font-sans sm:w-auto',
            $source,
            'Add-from-routines CTA must be full-width on mobile to avoid title wrap',
        );
        $this->assertStringContainsString(
            'grid grid-cols-3 gap-2',
            $source,
            'Meal remaining PFC metrics must sit in a readable three-column grid',
        );
    }

    private function componentSource(): string
    {
        $absolute = base_path('resources/js/components/routine/RoutinesHubTabs.vue');
        $this->assertFileExists($absolute);

        $contents = file_get_contents($absolute);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
