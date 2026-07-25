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
            'bg-primary/8 font-semibold text-primary',
            $source,
            'Active hub tab must use a tinted primary treatment for selection',
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

    public function test_today_progress_panel_centers_metrics(): void
    {
        $absolute = base_path(
            'resources/js/components/routine/TodayProgressPanel.vue',
        );
        $this->assertFileExists($absolute);
        $source = file_get_contents($absolute);
        $this->assertNotFalse($source);

        $this->assertStringContainsString(
            'flex w-full items-center justify-center gap-2',
            $source,
            'Date navigation row must be centered in the progress card',
        );
        $this->assertStringContainsString(
            'flex flex-col items-center rounded-xl bg-primary/10',
            $source,
            'Progress metric cells must center their icon and values',
        );
    }

    public function test_today_plan_card_vertically_centers_actions(): void
    {
        $absolute = base_path(
            'resources/js/components/routine/TodayPlanCard.vue',
        );
        $this->assertFileExists($absolute);
        $source = file_get_contents($absolute);
        $this->assertNotFalse($source);

        $this->assertStringContainsString(
            'flex items-center gap-3',
            $source,
            'Plan card row must vertically center icon and start button',
        );
        $this->assertStringContainsString(
            'class="min-w-0 flex-1"',
            $source,
            'Duration line must live inside the middle column so actions center against full content',
        );
        $this->assertStringNotContainsString(
            'pl-[3.25rem]',
            $source,
            'Regression guard: indented duration row pulled icon/button visually upward',
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
