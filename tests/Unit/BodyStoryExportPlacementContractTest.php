<?php

namespace Tests\Unit;

use Tests\TestCase;

class BodyStoryExportPlacementContractTest extends TestCase
{
    public function test_weekly_button_lives_in_records_meal_card(): void
    {
        $source = $this->pageSource('resources/js/pages/Records/Index.vue');
        $mealCard = strpos($source, 'aria-label="食事記録への入り口"');
        $weeklyButton = strpos($source, 'kind="weekly"');
        $conditionCard = strpos($source, 'aria-label="コンディション管理への入り口"');

        $this->assertNotFalse($mealCard);
        $this->assertNotFalse($weeklyButton);
        $this->assertNotFalse($conditionCard);
        $this->assertTrue(
            $mealCard < $weeklyButton && $weeklyButton < $conditionCard,
            'Weekly story button must sit inside the meal records card',
        );
        $this->assertStringContainsString('週の記録を画像にする', $source);
    }

    public function test_nutrition_button_lives_in_todays_meal_card(): void
    {
        $source = $this->pageSource('resources/js/pages/Meals/Index.vue');
        $card = strpos($source, 'aria-label="今日の食事記録"');
        $button = strpos($source, 'kind="nutrition"');

        $this->assertNotFalse($card);
        $this->assertNotFalse($button);
        $this->assertGreaterThan($card, $button);
        $this->assertStringContainsString('今日の食事を画像にする', $source);
    }

    public function test_weight_button_lives_in_condition_today_card(): void
    {
        $source = $this->pageSource('resources/js/pages/Records/Condition.vue');
        $card = strpos($source, 'aria-label="今日のコンディションを記録"');
        $button = strpos($source, 'kind="weight"');

        $this->assertNotFalse($card);
        $this->assertNotFalse($button);
        $this->assertGreaterThan($card, $button);
        $this->assertStringContainsString('今日の体重を画像にする', $source);
    }

    public function test_body_button_lives_in_condition_body_card(): void
    {
        $source = $this->pageSource('resources/js/pages/Records/Condition.vue');
        $card = strpos($source, 'aria-label="体組成PDFの取り込み"');
        $button = strpos($source, 'kind="body"');

        $this->assertNotFalse($card);
        $this->assertNotFalse($button);
        $this->assertGreaterThan($card, $button);
        $this->assertStringContainsString('体組成を画像にする', $source);
    }

    public function test_body_story_segment_values_sit_below_mass_labels(): void
    {
        $source = $this->pageSource('resources/js/lib/bodyStory/layout.ts');

        $this->assertTrue(
            preg_match('/leftArm: \{ lean: \{ x: \d+, y: (\d+) \}, fat: \{ x: \d+, y: (\d+) \}/', $source, $leftArm) === 1,
        );
        $this->assertTrue(
            preg_match('/torso: \{ lean: \{ x: \d+, y: (\d+) \}, fat: \{ x: \d+, y: (\d+) \}/', $source, $torso) === 1,
        );
        $this->assertTrue(
            preg_match('/leftLeg: \{ lean: \{ x: \d+, y: (\d+) \}, fat: \{ x: \d+, y: (\d+) \}/', $source, $leftLeg) === 1,
        );
        $this->assertTrue(
            preg_match('/rightArm: \{ lean: \{ x: \d+, y: (\d+) \}, fat: \{ x: \d+, y: (\d+) \}/', $source, $rightArm) === 1,
        );
        $this->assertTrue(
            preg_match('/rightLeg: \{ lean: \{ x: \d+, y: (\d+) \}, fat: \{ x: \d+, y: (\d+) \}/', $source, $rightLeg) === 1,
        );
        $this->assertTrue(
            preg_match('/abdominal: \{ x: \d+, y: (\d+) \}/', $source, $abdominal) === 1,
        );

        $this->assertGreaterThan(461, (int) $leftArm[1]);
        $this->assertLessThan(530, (int) $leftArm[1]);
        $this->assertGreaterThan(600, (int) $leftArm[2]);
        $this->assertLessThan(662, (int) $leftArm[2]);

        $this->assertGreaterThan(867, (int) $torso[1]);
        $this->assertLessThan(935, (int) $torso[1]);
        $this->assertGreaterThan(1005, (int) $torso[2]);
        $this->assertLessThan(1058, (int) $torso[2]);

        $this->assertGreaterThan(1245, (int) $leftLeg[1]);
        $this->assertLessThan(1292, (int) $leftLeg[1]);
        $this->assertGreaterThan(1360, (int) $leftLeg[2]);
        $this->assertLessThan(1405, (int) $leftLeg[2]);

        $this->assertSame((int) $leftArm[1], (int) $rightArm[1]);
        $this->assertSame((int) $leftArm[2], (int) $rightArm[2]);
        $this->assertSame((int) $leftLeg[1], (int) $rightLeg[1]);
        $this->assertSame((int) $leftLeg[2], (int) $rightLeg[2]);

        $this->assertGreaterThan(923, (int) $abdominal[1]);
        $this->assertLessThan(1031, (int) $abdominal[1]);
    }

    public function test_strength_page_has_lean_body_mass_chart_card(): void
    {
        $source = $this->pageSource('resources/js/pages/Records/Strength.vue');

        $this->assertStringContainsString('aria-label="徐脂肪体重の推移"', $source);
        $this->assertStringContainsString('leanBodyMassChartPoints', $source);
    }

    private function pageSource(string $relativePath): string
    {
        $absolute = base_path($relativePath);
        $this->assertFileExists($absolute);

        $contents = file_get_contents($absolute);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
