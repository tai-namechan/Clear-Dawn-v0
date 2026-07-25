<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove routines IA: no hub tabs, no sidebar programs, today prioritizes session + meal.
 */
class RoutinesHubTabsContractTest extends TestCase
{
    public function test_routines_hub_tabs_component_is_removed(): void
    {
        $this->assertFileDoesNotExist(
            base_path('resources/js/components/routine/RoutinesHubTabs.vue'),
            'Cross-page hub tabs are retired; page tabs on /routines own navigation',
        );
    }

    public function test_sidebar_does_not_list_programs(): void
    {
        $source = $this->pageSource('resources/js/components/AppSidebar.vue');

        $this->assertStringNotContainsString(
            "href: '/programs'",
            $source,
            'Programs must not appear in the sidebar',
        );
        $this->assertStringContainsString(
            "href: '/routines'",
            $source,
            'Routines remains the primary sidebar landing',
        );
    }

    public function test_satellite_pages_do_not_mount_hub_tabs(): void
    {
        foreach ([
            'resources/js/pages/History/Index.vue',
            'resources/js/pages/RoutineItems/Index.vue',
            'resources/js/pages/RoutineItems/Show.vue',
            'resources/js/pages/Routines/Show.vue',
            'resources/js/pages/Programs/Index.vue',
        ] as $relative) {
            $source = $this->pageSource($relative);

            $this->assertStringNotContainsString(
                'RoutinesHubTabs',
                $source,
                "{$relative} must not mount retired hub tabs",
            );
        }
    }

    public function test_programs_index_links_back_to_routines(): void
    {
        $source = $this->pageSource('resources/js/pages/Programs/Index.vue');

        $this->assertStringContainsString(
            'href="/routines"',
            $source,
            'Programs list must offer a back path to routines',
        );
        $this->assertStringContainsString(
            '← ルーティン',
            $source,
            'Back link label must point users to routines',
        );
    }

    public function test_routines_today_prioritizes_session_and_meal(): void
    {
        $source = $this->pageSource('resources/js/pages/Routines/Index.vue');

        $this->assertStringContainsString(
            'TodayProgressPanel',
            $source,
            'Today tab must lead with session progress',
        );
        $this->assertStringContainsString(
            'TodayPlanCard',
            $source,
            'Today tab must list plan cards for start/continue',
        );
        $this->assertStringContainsString(
            '食事の残り',
            $source,
            'Today tab must show remaining nutrition beside sessions',
        );
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
            'href="/programs"',
            $source,
            'Programs remains reachable from routines header/actions',
        );

        $sessionPos = strpos($source, '今日のセッション');
        $mealPos = strpos($source, '食事の残り');
        $opsPos = strpos($source, '<TodayOpsPrimary');
        $checkinPos = strpos($source, '今日のチェックイン');

        $this->assertNotFalse($sessionPos);
        $this->assertNotFalse($mealPos);
        $this->assertNotFalse($opsPos);
        $this->assertNotFalse($checkinPos);
        $this->assertTrue(
            $sessionPos < $opsPos && $mealPos < $opsPos,
            'Session + meal must render before ops',
        );
        $this->assertTrue(
            $opsPos < $checkinPos,
            'Ops must render before check-in',
        );
        $this->assertStringNotContainsString(
            '今日のメインセッション',
            $source,
            'Hero-style single primary session block is replaced by session list',
        );
        $this->assertStringNotContainsString(
            '/today?date=',
            $source,
            'Routines must not deep-link to the retired /today page for check-in/ops',
        );
    }

    public function test_routines_today_grid_children_can_shrink_on_narrow_viewports(): void
    {
        $index = $this->pageSource('resources/js/pages/Routines/Index.vue');
        $planCard = $this->pageSource(
            'resources/js/components/routine/TodayPlanCard.vue',
        );
        $progress = $this->pageSource(
            'resources/js/components/routine/TodayProgressPanel.vue',
        );

        $this->assertStringContainsString(
            'min-w-0 rounded-2xl border border-cd-line bg-cd-surface/70 p-4 shadow-sm md:p-5',
            $index,
            'Session and meal grid children must allow shrinking below content intrinsic width',
        );
        $this->assertStringContainsString(
            'class="group min-w-0 rounded-xl',
            $planCard,
            'Plan cards must shrink so CTA buttons stay inside the viewport',
        );
        $this->assertStringContainsString(
            'flex min-w-0 items-center gap-2',
            $planCard,
            'Plan card row must participate in min-width shrinking',
        );
        $this->assertStringContainsString(
            'cd-panel min-w-0 overflow-hidden',
            $progress,
            'Progress panel must not force horizontal overflow on narrow screens',
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

    private function pageSource(string $relative): string
    {
        $absolute = base_path($relative);
        $this->assertFileExists($absolute);

        $contents = file_get_contents($absolute);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
