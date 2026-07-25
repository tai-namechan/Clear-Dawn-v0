<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove shared page chrome (title / tabs / primary body) goes through PageTabShell.
 */
class PageTabShellContractTest extends TestCase
{
    public function test_shell_is_single_card_with_header_calendar_slot(): void
    {
        $source = $this->shellSource();

        foreach (['tabs', 'calendar', 'actions', 'badge'] as $slot) {
            $this->assertStringContainsString(
                "name=\"{$slot}\"",
                $source,
                "PageTabShell must expose #{$slot} slot",
            );
        }

        $this->assertStringContainsString(
            'PageTitleOrnament',
            $source,
            'Shell owns the shared page title ornament',
        );
        $this->assertStringContainsString(
            'justify-center md:justify-end',
            $source,
            'Calendar must be centered on mobile and right-aligned from md up',
        );
        $this->assertStringNotContainsString(
            'lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.8fr)]',
            $source,
            'Date calendar must not live in a separate side card',
        );
        $this->assertStringNotContainsString(
            'name="aside"',
            $source,
            'Aside side-card slot is retired in favor of #calendar in the header',
        );
    }

    public function test_meals_and_condition_put_compact_calendar_in_shell_header(): void
    {
        foreach ([
            'resources/js/pages/Meals/Index.vue',
            'resources/js/pages/Records/Condition.vue',
        ] as $relative) {
            $source = $this->pageSource($relative);

            $this->assertStringContainsString(
                'PageTabShell',
                $source,
                "{$relative} must render through PageTabShell",
            );
            $this->assertStringContainsString(
                '#calendar',
                $source,
                "{$relative} must place the date control in the shell calendar slot",
            );
            $this->assertStringContainsString(
                'compact',
                $source,
                "{$relative} must use the compact date control in the header",
            );
            $this->assertStringContainsString(
                'DateNavigator',
                $source,
                "{$relative} must keep date navigation",
            );
            $this->assertStringNotContainsString(
                '#aside',
                $source,
                "{$relative} must not use a separate aside date card",
            );
        }
    }

    public function test_today_and_routines_use_shell_for_primary_chrome(): void
    {
        $today = $this->pageSource('resources/js/pages/Today/Index.vue');
        $routines = $this->pageSource('resources/js/pages/Routines/Index.vue');

        $this->assertStringContainsString('PageTabShell', $today);
        $this->assertStringContainsString('RoutinesHubTabs', $today);
        $this->assertStringContainsString('#tabs', $today);

        $this->assertStringContainsString('PageTabShell', $routines);
        $this->assertStringContainsString('PageViewTabs', $routines);
        $this->assertStringContainsString('#tabs', $routines);
        $this->assertStringContainsString('#actions', $routines);
    }

    public function test_meals_keeps_secondary_logging_outside_shell(): void
    {
        $source = $this->pageSource('resources/js/pages/Meals/Index.vue');

        $shellClose = strpos($source, '</PageTabShell>');
        $this->assertNotFalse($shellClose);

        $afterShell = substr($source, $shellClose);
        $this->assertStringContainsString(
            '今日の食事記録',
            $afterShell,
            'Meal entry list stays outside the primary shell card',
        );
    }

    public function test_date_navigator_compact_mode_uses_calendar_icon(): void
    {
        $source = $this->pageSource('resources/js/components/DateNavigator.vue');

        $this->assertStringContainsString(
            'compact',
            $source,
            'DateNavigator must support compact header placement',
        );
        $this->assertStringContainsString(
            'CalendarDays',
            $source,
            'Compact mode must show a calendar affordance in the shell header',
        );
    }

    public function test_records_hub_puts_compact_calendar_in_shell_header(): void
    {
        $source = $this->pageSource('resources/js/pages/Records/Index.vue');

        $this->assertStringContainsString(
            'PageTabShell',
            $source,
            'Records hub must render through PageTabShell',
        );
        $this->assertStringContainsString(
            '#calendar',
            $source,
            'Records hub must place the date control in the shell calendar slot',
        );
        $this->assertStringContainsString(
            'compact',
            $source,
            'Records hub must use the compact date control in the header',
        );
        $this->assertStringNotContainsString(
            'lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.8fr)]',
            $source,
            'Records hub must not keep the date switcher as a separate side card',
        );
    }

    private function shellSource(): string
    {
        return $this->pageSource('resources/js/components/PageTabShell.vue');
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
