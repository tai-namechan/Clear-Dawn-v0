<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove shared page chrome (title / tabs / primary body) goes through PageTabShell.
 */
class PageTabShellContractTest extends TestCase
{
    public function test_shell_exposes_tabs_aside_and_actions_slots(): void
    {
        $source = $this->shellSource();

        foreach (['tabs', 'aside', 'actions', 'badge'] as $slot) {
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
    }

    public function test_meals_and_condition_use_shell_with_date_aside(): void
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
                '#aside',
                $source,
                "{$relative} must place DateNavigator in the shell aside slot",
            );
            $this->assertStringContainsString(
                'DateNavigator',
                $source,
                "{$relative} must keep date navigation",
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
