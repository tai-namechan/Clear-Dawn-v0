<?php

namespace Tests\Feature;

use App\Enums\MealType;
use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Models\NutritionGoal;
use App\Models\User;
use Database\Seeders\MatrixRowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MealEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
    }

    public function test_guests_cannot_access_meals(): void
    {
        $this->get(route('meals.index'))->assertRedirect(route('login'));
        $this->postJson(route('meals.store'), [])->assertUnauthorized();
        $this->putJson(route('meals.goals.upsert'), [])->assertUnauthorized();
    }

    public function test_user_cannot_see_or_mutate_another_users_meal_data(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $otherFood = FoodItem::factory()->for($other)->create(['name' => '他人の食品']);
        $otherEntry = MealEntry::factory()->for($other)->create([
            'eaten_on' => '2026-07-10',
            'name' => '他人の食事',
            'kcal' => 500,
        ]);
        NutritionGoal::factory()->for($other)->create(['kcal' => 3000]);

        $this->actingAs($user)
            ->get(route('meals.index', ['date' => '2026-07-10']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Meals/Index')
                ->where('totals.kcal', 0)
                ->where('goal', null)
                ->where('sections', fn ($sections) => collect($sections)->every(
                    fn (array $section): bool => count($section['entries']) === 0,
                ))
            );

        $this->actingAs($user)
            ->getJson(route('meals.foods.index', ['query' => '他人']))
            ->assertOk()
            ->assertJsonPath('foods', []);

        $this->actingAs($user)
            ->patchJson(route('meals.update', $otherEntry), [
                'eaten_on' => '2026-07-10',
                'meal_type' => MealType::Lunch->value,
                'name' => '改ざん',
                'quantity' => 1,
                'kcal' => 1,
                'protein_g' => 1,
                'fat_g' => 1,
                'carb_g' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->deleteJson(route('meals.destroy', $otherEntry))
            ->assertForbidden();

        $this->actingAs($user)
            ->patchJson(route('meals.foods.update', $otherFood), [
                'name' => '改ざん',
                'serving_label' => '1個',
                'kcal' => 1,
                'protein_g' => 1,
                'fat_g' => 1,
                'carb_g' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('meal_entries', [
            'id' => $otherEntry->id,
            'name' => '他人の食事',
            'kcal' => '500.00',
        ]);
    }

    public function test_snapshot_stays_independent_after_food_item_change_or_delete(): void
    {
        $user = User::factory()->create();
        $food = FoodItem::factory()->for($user)->create([
            'name' => 'ごはん',
            'kcal' => 100,
            'protein_g' => 10,
            'fat_g' => 5,
            'carb_g' => 20,
        ]);

        $this->actingAs($user)
            ->postJson(route('meals.store'), [
                'eaten_on' => '2026-07-10',
                'meal_type' => MealType::Breakfast->value,
                'food_item_id' => $food->id,
                'name' => 'ごはん',
                'quantity' => 1,
            ])
            ->assertCreated();

        $entry = MealEntry::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('100.00', (string) $entry->kcal);

        $this->actingAs($user)
            ->patchJson(route('meals.foods.update', $food), [
                'name' => 'ごはん',
                'serving_label' => $food->serving_label,
                'kcal' => 999,
                'protein_g' => 99,
                'fat_g' => 99,
                'carb_g' => 99,
            ])
            ->assertOk();

        $this->assertDatabaseHas('meal_entries', [
            'id' => $entry->id,
            'kcal' => '100.00',
            'protein_g' => '10.00',
            'fat_g' => '5.00',
            'carb_g' => '20.00',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('meals.foods.destroy', $food))
            ->assertOk();

        $this->assertSoftDeleted('food_items', ['id' => $food->id]);
        $this->assertDatabaseHas('meal_entries', [
            'id' => $entry->id,
            'kcal' => '100.00',
            'name' => 'ごはん',
        ]);
    }

    public function test_quantity_scales_nutrition_snapshot_from_food_item(): void
    {
        $user = User::factory()->create();
        $food = FoodItem::factory()->for($user)->create([
            'kcal' => 100,
            'protein_g' => 10,
            'fat_g' => 4,
            'carb_g' => 20,
        ]);

        $this->actingAs($user)
            ->postJson(route('meals.store'), [
                'eaten_on' => '2026-07-10',
                'meal_type' => MealType::Lunch->value,
                'food_item_id' => $food->id,
                'name' => $food->name,
                'quantity' => 1.5,
            ])
            ->assertCreated()
            ->assertJsonPath('entry.kcal', '150.00')
            ->assertJsonPath('entry.protein_g', '15.00')
            ->assertJsonPath('entry.fat_g', '6.00')
            ->assertJsonPath('entry.carb_g', '30.00');
    }

    public function test_daily_totals_and_section_subtotals_are_correct(): void
    {
        $user = User::factory()->create();

        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-07-10',
            'meal_type' => MealType::Breakfast,
            'kcal' => 200,
            'protein_g' => 10,
            'fat_g' => 5,
            'carb_g' => 20,
        ]);
        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-07-10',
            'meal_type' => MealType::Breakfast,
            'kcal' => 100,
            'protein_g' => 5,
            'fat_g' => 2,
            'carb_g' => 10,
        ]);
        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-07-10',
            'meal_type' => MealType::Dinner,
            'kcal' => 400,
            'protein_g' => 30,
            'fat_g' => 15,
            'carb_g' => 40,
        ]);
        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-07-09',
            'meal_type' => MealType::Snack,
            'kcal' => 999,
            'protein_g' => 99,
            'fat_g' => 99,
            'carb_g' => 99,
        ]);

        $this->actingAs($user)
            ->get(route('meals.index', ['date' => '2026-07-10']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('totals.kcal', 700)
                ->where('totals.protein_g', 45)
                ->where('totals.fat_g', 22)
                ->where('totals.carb_g', 70)
                ->where('sections', function ($sections) {
                    $byType = collect($sections)->keyBy('meal_type');

                    return (float) $byType['breakfast']['subtotal']['kcal'] === 300.0
                        && (float) $byType['dinner']['subtotal']['kcal'] === 400.0
                        && (float) $byType['lunch']['subtotal']['kcal'] === 0.0
                        && (float) $byType['snack']['subtotal']['kcal'] === 0.0;
                })
            );
    }

    public function test_validation_rejects_out_of_range_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('meals.store'), [
                'eaten_on' => '2026-07-10',
                'meal_type' => MealType::Snack->value,
                'name' => '過多',
                'quantity' => 0.05,
                'kcal' => 10000,
                'protein_g' => -1,
                'fat_g' => 1,
                'carb_g' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity', 'kcal', 'protein_g']);

        $this->actingAs($user)
            ->putJson(route('meals.goals.upsert'), [
                'kcal' => 30000,
                'protein_g' => 10,
                'fat_g' => 10,
                'carb_g' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['kcal']);
    }

    public function test_nutrition_goals_upsert_updates_on_second_put(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson(route('meals.goals.upsert'), [
                'kcal' => 2000,
                'protein_g' => 100,
                'fat_g' => 60,
                'carb_g' => 220,
            ])
            ->assertOk();

        $this->actingAs($user)
            ->putJson(route('meals.goals.upsert'), [
                'kcal' => 2100,
                'protein_g' => 110,
                'fat_g' => 65,
                'carb_g' => 230,
            ])
            ->assertOk()
            ->assertJsonPath('goal.kcal', '2100.00');

        $this->assertSame(1, NutritionGoal::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('nutrition_goals', [
            'user_id' => $user->id,
            'kcal' => '2100.00',
            'protein_g' => '110.00',
        ]);
    }

    public function test_direct_entry_can_register_as_food_item(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('meals.store'), [
                'eaten_on' => '2026-07-10',
                'meal_type' => MealType::Snack->value,
                'name' => 'プロテイン',
                'quantity' => 2,
                'kcal' => 200,
                'protein_g' => 40,
                'fat_g' => 2,
                'carb_g' => 10,
                'register_as_food' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('food_items', [
            'user_id' => $user->id,
            'name' => 'プロテイン',
            'kcal' => '100.00',
            'protein_g' => '20.00',
        ]);
        $this->assertDatabaseHas('meal_entries', [
            'user_id' => $user->id,
            'name' => 'プロテイン',
            'kcal' => '200.00',
        ]);
    }

    public function test_records_index_includes_meal_totals_card_data(): void
    {
        $user = User::factory()->create();
        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-07-10',
            'kcal' => 450,
        ]);

        $this->actingAs($user)
            ->get(route('records.index', ['date' => '2026-07-10']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Records/Index')
                ->where('mealTotals.kcal', 450)
                ->has('mealSections')
            );
    }

    public function test_user_can_copy_previous_day_meals_scoped_to_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-07-09',
            'meal_type' => MealType::Breakfast,
            'name' => '昨日の朝食',
            'kcal' => 400,
            'protein_g' => 20,
            'fat_g' => 10,
            'carb_g' => 50,
        ]);
        MealEntry::factory()->for($other)->create([
            'eaten_on' => '2026-07-09',
            'name' => '他人の食事',
            'kcal' => 999,
        ]);

        $this->actingAs($user)
            ->postJson(route('meals.copy-previous-day'), ['date' => '2026-07-10'])
            ->assertOk()
            ->assertJsonPath('copied', 1);

        $this->assertTrue(
            MealEntry::query()
                ->where('user_id', $user->id)
                ->whereDate('eaten_on', '2026-07-10')
                ->where('name', '昨日の朝食')
                ->where('kcal', 400)
                ->exists(),
        );
        $this->assertFalse(
            MealEntry::query()
                ->where('user_id', $user->id)
                ->whereDate('eaten_on', '2026-07-10')
                ->where('name', '他人の食事')
                ->exists(),
        );
    }

    public function test_copy_previous_day_preserves_meal_type_and_pfc(): void
    {
        $user = User::factory()->create();

        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-07-09',
            'meal_type' => MealType::Dinner,
            'name' => '鶏むね定食',
            'quantity' => 1.5,
            'kcal' => 620,
            'protein_g' => 45,
            'fat_g' => 12,
            'carb_g' => 70,
            'note' => '大盛り',
        ]);

        $this->actingAs($user)
            ->postJson(route('meals.copy-previous-day'), ['date' => '2026-07-10'])
            ->assertOk()
            ->assertJsonPath('copied', 1);

        $copy = MealEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('eaten_on', '2026-07-10')
            ->firstOrFail();

        $this->assertSame(MealType::Dinner, $copy->meal_type);
        $this->assertSame('鶏むね定食', $copy->name);
        $this->assertEquals(620, $copy->kcal);
        $this->assertEquals(45, $copy->protein_g);
        $this->assertEquals(12, $copy->fat_g);
        $this->assertEquals(70, $copy->carb_g);
        $this->assertSame('大盛り', $copy->note);
    }

    public function test_copy_previous_day_is_rejected_when_target_day_already_has_entries(): void
    {
        $user = User::factory()->create();

        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-07-09',
            'name' => '昨日の朝食',
        ]);
        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-07-10',
            'name' => '既存の記録',
        ]);

        $this->actingAs($user)
            ->postJson(route('meals.copy-previous-day'), ['date' => '2026-07-10'])
            ->assertOk()
            ->assertJsonPath('copied', 0)
            ->assertJsonPath('reason', 'target_not_empty');

        $this->assertSame(
            1,
            MealEntry::query()
                ->where('user_id', $user->id)
                ->whereDate('eaten_on', '2026-07-10')
                ->count(),
        );
    }

    public function test_copy_previous_day_double_submit_does_not_duplicate(): void
    {
        $user = User::factory()->create();

        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-07-09',
            'name' => '昨日の朝食',
        ]);

        $this->actingAs($user)
            ->postJson(route('meals.copy-previous-day'), ['date' => '2026-07-10'])
            ->assertOk()
            ->assertJsonPath('copied', 1);

        $this->actingAs($user)
            ->postJson(route('meals.copy-previous-day'), ['date' => '2026-07-10'])
            ->assertOk()
            ->assertJsonPath('copied', 0)
            ->assertJsonPath('reason', 'target_not_empty');

        $this->assertSame(
            1,
            MealEntry::query()
                ->where('user_id', $user->id)
                ->whereDate('eaten_on', '2026-07-10')
                ->count(),
        );
    }

    public function test_copy_previous_day_with_empty_source_copies_nothing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('meals.copy-previous-day'), ['date' => '2026-07-10'])
            ->assertOk()
            ->assertJsonPath('copied', 0)
            ->assertJsonPath('reason', 'source_empty');
    }

    public function test_guests_cannot_copy_previous_day(): void
    {
        $this->postJson(route('meals.copy-previous-day'), ['date' => '2026-07-10'])
            ->assertUnauthorized();
    }
}
