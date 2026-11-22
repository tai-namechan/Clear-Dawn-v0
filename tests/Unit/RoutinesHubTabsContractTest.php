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
        // The sidebar and the mobile nav share one definition (docs/clear-dawn-hero-design-guidelines.md).
        $sidebar = $this->pageSource('resources/js/components/AppSidebar.vue');
        $navigation = $this->pageSource('resources/js/lib/clearDawnNavigation.mjs');

        $this->assertStringContainsString(
            'clearDawnNavigation',
            $sidebar,
            'The sidebar must read the shared navigation definition',
        );
        $this->assertStringNotContainsString(
            "href: '/programs'",
            $navigation,
            'Programs must not appear in the sidebar',
        );
        $this->assertStringContainsString(
            "href: '/routines'",
            $navigation,
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

    public function test_routines_today_focuses_on_sessions(): void
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
        $this->assertStringNotContainsString(
            '食事の残り',
            $source,
            'Meal status lives on the meals screen, not the routines today tab (2026-11-22)',
        );
        $this->assertStringNotContainsString(
            'TodayOpsPrimary',
            $source,
            'The ops card is no longer shown on the routines today tab (2026-11-22)',
        );
        $this->assertStringNotContainsString(
            'DailyCheckinPanel',
            $source,
            'The 30-second check-in was retired on 2026-11-22 (docs/product/screens/today-ops.md)',
        );
        $this->assertStringContainsString(
            'href="/programs"',
            $source,
            'Programs remains reachable from routines header/actions',
        );

        $this->assertNotFalse(
            strpos($source, '今日のセッション'),
            'Today tab must list today\'s sessions',
        );
        $this->assertStringNotContainsString(
            '今日のチェックイン',
            $source,
            'No check-in block remains on the routines today tab',
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
            'min-w-0 rounded-2xl border border-cd-line bg-white p-4 shadow-sm md:p-5',
            $index,
            'Session and meal grid children must allow shrinking below content intrinsic width',
        );
        $this->assertStringContainsString(
            'class="group min-w-0 rounded-2xl',
            $planCard,
            'Plan cards must shrink so CTA buttons stay inside the viewport',
        );
        $this->assertStringContainsString(
            'flex min-w-0 items-center gap-2',
            $planCard,
            'Plan card row must participate in min-width shrinking',
        );
        $this->assertStringContainsString(
            'min-w-0 overflow-hidden rounded-2xl',
            $progress,
            'Progress panel must not force horizontal overflow on narrow screens',
        );
        $this->assertStringContainsString(
            'items-center justify-center text-center',
            $progress,
            'Date label and today caption must be centered between day arrows',
        );
        $this->assertStringContainsString(
            'type="date"',
            $progress,
            'Calendar icon must open a date picker for direct date jumps',
        );
        $this->assertStringContainsString(
            '日付を選択',
            $progress,
            'Calendar control must expose a clear date-select label',
        );
    }

    public function test_choice_days_are_selected_from_the_session_card(): void
    {
        $index = $this->pageSource('resources/js/pages/Routines/Index.vue');
        $planCard = $this->pageSource(
            'resources/js/components/routine/TodayPlanCard.vue',
        );
        $this->assertStringContainsString(
            ':choice-context="choiceContextFor(plan.id)"',
            $index,
        );
        $this->assertStringContainsString(
            '今日行う内容を選択してください',
            $planCard,
        );
        $this->assertStringContainsString(
            'status === \'not_started\' && !needsChoice',
            $planCard,
        );
        foreach (['IconBarbell', 'IconYoga', 'IconRun', 'IconBed'] as $icon) {
            $this->assertStringContainsString($icon, $planCard);
        }
        $this->assertStringNotContainsString(
            '今日のプログラム選択',
            $index,
        );
    }

    public function test_ops_card_component_is_retired(): void
    {
        $this->assertFileDoesNotExist(
            base_path('resources/js/components/routine/TodayOpsPrimary.vue'),
            'The ops card was removed from the routines today tab on 2026-11-22',
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
