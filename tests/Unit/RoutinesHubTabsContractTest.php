<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove RoutinesHubTabs keeps URL-based hub navigation while using
 * underline-tab visuals (same family as PageViewTabs).
 */
class RoutinesHubTabsContractTest extends TestCase
{
    public function test_hub_tabs_keep_routines_and_history_only(): void
    {
        $source = $this->componentSource();

        foreach (['/routines', '/history'] as $href) {
            $this->assertStringContainsString(
                "href: '{$href}'",
                $source,
                "Hub tab must keep navigation URL {$href}",
            );
        }

        $this->assertStringNotContainsString(
            "href: '/today'",
            $source,
            '今日/作戦 was folded into /routines today tab',
        );
        $this->assertStringNotContainsString(
            "href: '/programs'",
            $source,
            'Programs list stays reachable via sidebar/header, not hub tabs',
        );
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

    public function test_routines_today_owns_ops_and_session_start(): void
    {
        $source = $this->pageSource('resources/js/pages/Routines/Index.vue');

        $this->assertStringContainsString(
            'TodayOpsPrimary',
            $source,
            'Ops primary card lives on /routines today tab after fold-in',
        );
        $this->assertStringContainsString(
            'DailyCheckinPanel',
            $source,
            'Check-in UI lives on /routines today tab (single instance)',
        );
        $this->assertStringContainsString(
            '今日のメインセッション',
            $source,
            'Session start remains on /routines today tab',
        );
        $this->assertStringNotContainsString(
            '/today?date=',
            $source,
            'Routines must not deep-link to the retired /today page for check-in/ops',
        );
    }

    public function test_today_ops_primary_skips_checkin_nudge_cards(): void
    {
        $source = $this->pageSource(
            'resources/js/components/routine/TodayOpsPrimary.vue',
        );

        $this->assertStringContainsString(
            'isCheckinNudge',
            $source,
            'Primary ops must filter check-in nudge recommendation cards',
        );
    }

    public function test_programs_index_is_standalone_without_hub_tabs(): void
    {
        $source = $this->pageSource('resources/js/pages/Programs/Index.vue');

        $this->assertStringNotContainsString(
            'RoutinesHubTabs',
            $source,
            'Programs list is a sidebar destination and does not need routines hub tabs',
        );
        $this->assertStringContainsString(
            'プログラム一覧',
            $source,
            'Programs list screen itself remains',
        );
    }

    private function componentSource(): string
    {
        return $this->pageSource(
            'resources/js/components/routine/RoutinesHubTabs.vue',
        );
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
