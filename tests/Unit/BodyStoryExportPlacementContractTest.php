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

    private function pageSource(string $relativePath): string
    {
        $absolute = base_path($relativePath);
        $this->assertFileExists($absolute);

        $contents = file_get_contents($absolute);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
