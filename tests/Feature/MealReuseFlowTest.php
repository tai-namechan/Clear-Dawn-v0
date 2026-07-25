<?php

namespace Tests\Feature;

use App\Enums\FoodLookupStatus;
use App\Enums\MealType;
use App\Models\FoodItem;
use App\Models\FoodLookupRequest;
use App\Models\MealEntry;
use App\Models\User;
use Database\Seeders\MatrixRowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealReuseFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MatrixRowSeeder::class);
    }

    public function test_confirm_with_add_to_meal_creates_food_and_entry(): void
    {
        $user = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($user)->found()->create([
            'barcode' => '4901234567894',
            'barcode_type' => 'ean13',
            'source' => 'openfoodfacts',
        ]);

        $response = $this->actingAs($user)->postJson(
            route('meals.barcode-lookup.confirm', $lookup->id),
            [
                'name' => 'テスト飲料',
                'serving_label' => '1本',
                'kcal' => 200,
                'protein_g' => 1,
                'fat_g' => 0,
                'carb_g' => 48,
                'brand' => 'テスト社',
                'nutrition_basis' => 'serving',
                'add_to_meal' => true,
                'eaten_on' => '2026-07-25',
                'meal_type' => MealType::Lunch->value,
                'quantity' => 1.5,
            ],
        );

        $response->assertCreated()
            ->assertJsonPath('food.name', 'テスト飲料')
            ->assertJsonPath('food.brand', 'テスト社')
            ->assertJsonPath('food.nutrition_basis', 'serving')
            ->assertJsonPath('entry.meal_type', MealType::Lunch->value)
            ->assertJsonPath('entry.quantity', '1.50')
            ->assertJsonPath('entry.kcal', '300.00')
            ->assertJsonPath('created', true);

        $this->assertDatabaseHas('food_items', [
            'user_id' => $user->id,
            'barcode' => '4901234567894',
            'name' => 'テスト飲料',
            'brand' => 'テスト社',
        ]);

        $this->assertDatabaseHas('meal_entries', [
            'user_id' => $user->id,
            'name' => 'テスト飲料',
            'kcal' => '300.00',
        ]);
        $this->assertSame(
            1,
            MealEntry::query()
                ->where('user_id', $user->id)
                ->whereDate('eaten_on', '2026-07-25')
                ->count(),
        );
    }

    public function test_confirm_with_add_to_meal_is_idempotent_on_repeat(): void
    {
        $user = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($user)->found()->create([
            'barcode' => '4901234567894',
            'barcode_type' => 'ean13',
        ]);

        $payload = [
            'name' => 'テスト飲料',
            'serving_label' => '1本',
            'kcal' => 200,
            'protein_g' => 1,
            'fat_g' => 0,
            'carb_g' => 48,
            'add_to_meal' => true,
            'eaten_on' => '2026-07-25',
            'meal_type' => MealType::Breakfast->value,
            'quantity' => 1,
        ];

        $first = $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.confirm', $lookup->id), $payload)
            ->assertCreated()
            ->json();

        $second = $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.confirm', $lookup->id), $payload)
            ->assertOk()
            ->json();

        $this->assertSame($first['entry']['id'], $second['entry']['id']);
        $this->assertFalse($second['created']);
        $this->assertSame(1, MealEntry::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, FoodItem::query()->where('user_id', $user->id)->count());
    }

    public function test_manual_food_and_meal_registration(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('meals.foods.manual.store'), [
                'save_mode' => 'food_and_meal',
                'name' => '手入力おにぎり',
                'serving_label' => '1個',
                'kcal' => 180,
                'protein_g' => 4,
                'fat_g' => 1,
                'carb_g' => 37,
                'barcode' => '4901234567894',
                'brand' => 'コンビニ',
                'nutrition_basis' => 'serving',
                'eaten_on' => '2026-07-25',
                'meal_type' => MealType::Snack->value,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('food.name', '手入力おにぎり')
            ->assertJsonPath('entry.name', '手入力おにぎり');

        $this->assertDatabaseHas('food_items', [
            'user_id' => $user->id,
            'barcode' => '4901234567894',
            'source' => 'manual',
        ]);
    }

    public function test_one_off_direct_entry_does_not_create_food_item(): void
    {
        $user = User::factory()->create();
        $cntBefore = FoodItem::query()->count();

        $this->actingAs($user)
            ->postJson(route('meals.foods.manual.store'), [
                'save_mode' => 'one_off',
                'name' => '今回だけケーキ',
                'kcal' => 350,
                'protein_g' => 5,
                'fat_g' => 15,
                'carb_g' => 45,
                'eaten_on' => '2026-07-25',
                'meal_type' => MealType::Dinner->value,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('food', null)
            ->assertJsonPath('entry.name', '今回だけケーキ');

        $this->assertSame($cntBefore, FoodItem::query()->count());
        $this->assertDatabaseHas('meal_entries', [
            'user_id' => $user->id,
            'name' => '今回だけケーキ',
            'food_item_id' => null,
        ]);
    }

    public function test_favorite_toggle_requires_ownership(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $food = FoodItem::factory()->for($other)->create();

        $this->actingAs($user)
            ->patchJson(route('meals.foods.favorite', $food), ['is_favorite' => true])
            ->assertForbidden();

        $own = FoodItem::factory()->for($user)->create(['is_favorite' => false]);

        $this->actingAs($user)
            ->patchJson(route('meals.foods.favorite', $own), ['is_favorite' => true])
            ->assertOk()
            ->assertJsonPath('food.is_favorite', true);

        $this->assertDatabaseHas('food_items', [
            'id' => $own->id,
            'is_favorite' => 1,
        ]);
    }

    public function test_usual_foods_returns_favorites_recent_frequent_without_duplicates(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $favorite = FoodItem::factory()->for($user)->create([
            'name' => 'お気に入りご飯',
            'is_favorite' => true,
        ]);
        $recent = FoodItem::factory()->for($user)->create(['name' => '最近のパン']);
        $frequent = FoodItem::factory()->for($user)->create(['name' => 'よく使う納豆']);
        $otherFood = FoodItem::factory()->for($other)->create(['name' => '他人の食品', 'is_favorite' => true]);

        MealEntry::factory()->for($user)->create([
            'food_item_id' => $recent->id,
            'eaten_on' => '2026-07-24',
            'created_at' => now()->subHour(),
        ]);
        MealEntry::factory()->for($user)->count(3)->create([
            'food_item_id' => $frequent->id,
            'eaten_on' => '2026-07-20',
        ]);
        MealEntry::factory()->for($user)->create([
            'food_item_id' => $favorite->id,
            'eaten_on' => '2026-07-23',
        ]);
        MealEntry::factory()->for($other)->create([
            'food_item_id' => $otherFood->id,
            'eaten_on' => '2026-07-24',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('meals.foods.index', ['group' => 'usual']))
            ->assertOk();

        $favorites = collect($response->json('favorites'))->pluck('id');
        $recentIds = collect($response->json('recent'))->pluck('id');
        $frequentIds = collect($response->json('frequent'))->pluck('id');

        $this->assertTrue($favorites->contains($favorite->id));
        $this->assertTrue($frequentIds->contains($frequent->id));
        // 利用回数が少ない recent は frequent に入るか、frequent 除外後の recent に入る
        $this->assertTrue(
            $recentIds->contains($recent->id) || $frequentIds->contains($recent->id),
        );
        $this->assertFalse($recentIds->contains($favorite->id));
        $this->assertFalse($frequentIds->contains($favorite->id));
        $this->assertFalse($recentIds->contains($frequent->id));
        $this->assertFalse($favorites->contains($otherFood->id));
        $this->assertFalse($frequentIds->contains($otherFood->id));
    }

    public function test_copy_individual_meal_entry_to_target_date_and_meal_type(): void
    {
        $user = User::factory()->create();
        $source = MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-07-20',
            'meal_type' => MealType::Breakfast,
            'name' => '朝のプロテイン',
            'quantity' => 1,
            'kcal' => 120,
            'protein_g' => 20,
            'fat_g' => 2,
            'carb_g' => 4,
        ]);

        // 同日に既存記録があっても個別コピーは可能
        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-07-25',
            'meal_type' => MealType::Lunch,
            'name' => '既存の昼食',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('meals.copy', $source), [
                'eaten_on' => '2026-07-25',
                'meal_type' => MealType::Snack->value,
                'quantity' => 2,
            ])
            ->assertCreated();

        $response->assertJsonPath('entry.name', '朝のプロテイン')
            ->assertJsonPath('entry.meal_type', MealType::Snack->value)
            ->assertJsonPath('entry.quantity', '2.00')
            ->assertJsonPath('entry.kcal', '240.00');

        $source->refresh();
        $this->assertSame('2026-07-20', $source->eaten_on->toDateString());
        $this->assertSame(MealType::Breakfast, $source->meal_type);
        $this->assertEquals(1, (float) $source->quantity);

        $this->assertSame(3, MealEntry::query()->where('user_id', $user->id)->count());
    }

    public function test_copy_individual_meal_entry_scoped_to_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $source = MealEntry::factory()->for($other)->create([
            'eaten_on' => '2026-07-20',
        ]);

        $this->actingAs($user)
            ->postJson(route('meals.copy', $source), [
                'eaten_on' => '2026-07-25',
            ])
            ->assertForbidden();
    }

    public function test_confirm_food_only_still_works_without_meal(): void
    {
        $user = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($user)->found()->create([
            'status' => FoodLookupStatus::Found,
        ]);

        $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.confirm', $lookup->id), [
                'name' => 'マイ食品のみ',
                'serving_label' => '100g',
                'kcal' => 100,
                'protein_g' => 10,
                'fat_g' => 1,
                'carb_g' => 5,
            ])
            ->assertCreated()
            ->assertJsonMissingPath('entry');

        $this->assertSame(0, MealEntry::query()->where('user_id', $user->id)->count());
    }
}
