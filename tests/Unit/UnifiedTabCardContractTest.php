<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Prove unified tab+content card layout (recommendation A):
 * title/tabs and primary content share one surface.
 */
class UnifiedTabCardContractTest extends TestCase
{
    public function test_today_ops_merges_hub_tabs_with_primary_recommendation(): void
    {
        $source = $this->source('resources/js/pages/Today/Index.vue');

        $this->assertMatchesRegularExpression(
            '/PageSectionCard[^>]*>[\s\S]*RoutinesHubTabs[\s\S]*primaryRecommendation[\s\S]*<\/PageSectionCard>/',
            $source,
            'Today page must keep hub tabs and primary ops content in the same PageSectionCard',
        );
        $this->assertStringContainsString(
            'border-t border-cd-line pt-5',
            $source,
            'Unified card uses an internal divider under tabs, not a second outer card',
        );
    }

    public function test_routines_merges_page_tabs_with_today_hero(): void
    {
        $source = $this->source('resources/js/pages/Routines/Index.vue');

        $this->assertMatchesRegularExpression(
            '/PageViewTabs[\s\S]*今日のメインセッション[\s\S]*primaryPlan\.title/',
            $source,
            'Routines today hero must live under PageViewTabs in the header card',
        );
        // Separate hero PageSectionCard for primary plan should be gone
        $this->assertDoesNotMatchRegularExpression(
            '/PageSectionCard\s*\n\s*v-if="primaryPlan"\s*\n\s*aria-label="今日のメインセッション"/',
            $source,
            'Regression guard: today hero must not be a separate PageSectionCard',
        );
    }

    private function source(string $relativePath): string
    {
        $absolute = base_path($relativePath);
        $this->assertFileExists($absolute);
        $contents = file_get_contents($absolute);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
