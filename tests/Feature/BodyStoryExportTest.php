<?php

namespace Tests\Feature;

use App\Enums\MealType;
use App\Models\MealEntry;
use App\Models\Metric;
use App\Models\MetricRecord;
use App\Models\User;
use Database\Seeders\MatrixRowSeeder;
use Database\Seeders\MetricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BodyStoryExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
        $this->seed(MetricSeeder::class);
    }

    public function test_guests_cannot_export_story_data(): void
    {
        $this->getJson(route('records.story-export', [
            'kind' => 'nutrition',
            'date' => '2026-08-16',
        ]))->assertUnauthorized();
    }

    public function test_story_export_reads_meal_entries_without_writing(): void
    {
        $user = User::factory()->create();

        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-08-16',
            'meal_type' => MealType::Lunch,
            'kcal' => 620,
            'protein_g' => 48,
            'fat_g' => 12,
            'carb_g' => 78,
        ]);
        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-08-16',
            'meal_type' => MealType::Dinner,
            'kcal' => 1860,
            'protein_g' => 132,
            'fat_g' => 56,
            'carb_g' => 207,
        ]);

        $mealCount = MealEntry::query()->count();

        $this->actingAs($user)
            ->getJson(route('records.story-export', [
                'kind' => 'nutrition',
                'date' => '2026-08-16',
            ]))
            ->assertOk()
            ->assertJsonPath('kind', 'nutrition')
            ->assertJsonPath('date', '2026-08-16')
            ->assertJsonPath('template_url', '/images/products/today-food-log.png')
            ->assertJsonPath('nutrition.calories_kcal', 2480)
            ->assertJsonPath('nutrition.protein_g', 180)
            ->assertJsonPath('nutrition.fat_g', 68)
            ->assertJsonPath('nutrition.carbs_g', 285);

        $this->assertSame($mealCount, MealEntry::query()->count());
    }

    public function test_story_export_excludes_other_users_meals_and_weight(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $metric = Metric::query()->where('key', 'weight')->firstOrFail();

        MealEntry::factory()->for($other)->create([
            'eaten_on' => '2026-08-16',
            'kcal' => 9999,
            'protein_g' => 999,
            'fat_g' => 99,
            'carb_g' => 999,
        ]);
        MetricRecord::factory()->create([
            'user_id' => $other->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-08-16',
            'value' => 120,
        ]);

        $this->actingAs($user)
            ->getJson(route('records.story-export', [
                'kind' => 'weekly',
                'date' => '2026-08-16',
            ]))
            ->assertOk()
            ->assertJsonPath('nutrition.calories_kcal', null)
            ->assertJsonPath('nutrition.calorie_history', [])
            ->assertJsonPath('weight.weight_kg', null)
            ->assertJsonPath('weight.history', []);
    }

    public function test_weight_delta_uses_previous_calendar_day_only(): void
    {
        $user = User::factory()->create();
        $metric = Metric::query()->where('key', 'weight')->firstOrFail();

        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-08-15',
            'value' => 85.0,
        ]);
        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-08-16',
            'value' => 84.6,
        ]);

        $this->actingAs($user)
            ->getJson(route('records.story-export', [
                'kind' => 'weight',
                'date' => '2026-08-16',
            ]))
            ->assertOk()
            ->assertJsonPath('template_url', '/images/products/today-weight-log.png')
            ->assertJsonPath('weight.weight_kg', 84.6)
            ->assertJsonPath('weight.previous_weight_kg', 85)
            ->assertJsonPath('weight.delta_kg', -0.4);
    }

    public function test_weight_delta_is_null_when_previous_day_is_missing(): void
    {
        $user = User::factory()->create();
        $metric = Metric::query()->where('key', 'weight')->firstOrFail();

        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-08-14',
            'value' => 86.0,
        ]);
        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-08-16',
            'value' => 84.6,
        ]);

        $this->actingAs($user)
            ->getJson(route('records.story-export', [
                'kind' => 'weight',
                'date' => '2026-08-16',
            ]))
            ->assertOk()
            ->assertJsonPath('weight.delta_kg', null)
            ->assertJsonPath('weight.previous_weight_kg', null)
            ->assertJsonPath('weight.average_7d_kg', 85.3);
    }

    public function test_weekly_averages_ignore_days_without_records(): void
    {
        $user = User::factory()->create();
        $metric = Metric::query()->where('key', 'weight')->firstOrFail();

        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-08-10',
            'kcal' => 2000,
            'protein_g' => 100,
            'fat_g' => 50,
            'carb_g' => 200,
        ]);
        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-08-16',
            'kcal' => 3000,
            'protein_g' => 200,
            'fat_g' => 80,
            'carb_g' => 300,
        ]);
        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-08-10',
            'value' => 86,
        ]);
        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-08-16',
            'value' => 84,
        ]);

        $this->actingAs($user)
            ->getJson(route('records.story-export', [
                'kind' => 'weekly',
                'date' => '2026-08-16',
            ]))
            ->assertOk()
            ->assertJsonPath('start_date', '2026-08-10')
            ->assertJsonPath('end_date', '2026-08-16')
            ->assertJsonPath('template_url', '/images/products/weekly-log.png')
            ->assertJsonPath('nutrition.average_calories_kcal', 2500)
            ->assertJsonPath('weight.average_7d_kg', 85)
            ->assertJsonPath('nutrition.calorie_history.0.date', '2026-08-10')
            ->assertJsonPath('nutrition.calorie_history.1.date', '2026-08-16');
    }

    public function test_story_history_is_capped_to_seven_days(): void
    {
        $user = User::factory()->create();
        $metric = Metric::query()->where('key', 'weight')->firstOrFail();

        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-08-09',
            'value' => 90,
        ]);
        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-08-10',
            'value' => 85,
        ]);
        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-08-09',
            'kcal' => 5000,
        ]);
        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-08-16',
            'kcal' => 2000,
        ]);

        $this->actingAs($user)
            ->getJson(route('records.story-export', [
                'kind' => 'weekly',
                'date' => '2026-08-16',
            ]))
            ->assertOk()
            ->assertJsonPath('nutrition.calorie_history.0.date', '2026-08-16')
            ->assertJsonPath('weight.history.0.date', '2026-08-10')
            ->assertJsonCount(1, 'nutrition.calorie_history')
            ->assertJsonCount(1, 'weight.history');
    }

    public function test_invalid_kind_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('records.story-export', [
                'kind' => 'recap',
                'date' => '2026-08-16',
            ]))
            ->assertUnprocessable();
    }

    public function test_story_templates_exist_in_public(): void
    {
        foreach ([
            'images/products/today-food-log.png',
            'images/products/today-weight-log.png',
            'images/products/weekly-log.png',
        ] as $relativePath) {
            $this->assertFileExists(public_path($relativePath));
        }
    }
}
