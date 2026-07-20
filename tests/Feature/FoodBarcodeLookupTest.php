<?php

namespace Tests\Feature;

use App\Enums\FoodLookupStatus;
use App\Jobs\LookupOpenFoodFactsJob;
use App\Models\FoodItem;
use App\Models\FoodLookupRequest;
use App\Models\User;
use Database\Seeders\MatrixRowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FoodBarcodeLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MatrixRowSeeder::class);
    }

    public function test_guests_cannot_access_barcode_endpoints(): void
    {
        $this->postJson(route('meals.barcode-lookup.store'), ['barcode' => '4901234567894'])
            ->assertUnauthorized();
    }

    public function test_store_returns_hit_when_food_already_exists(): void
    {
        $user = User::factory()->create();
        $food = FoodItem::factory()->for($user)->create([
            'barcode' => '4901234567894',
            'barcode_type' => 'ean13',
            'name' => '既存食品',
        ]);

        Queue::fake();

        $response = $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.store'), ['barcode' => '4901234567894']);

        $response->assertOk()
            ->assertJson([
                'status' => 'hit',
                'food' => ['id' => $food->id, 'name' => '既存食品'],
            ]);

        Queue::assertNothingPushed();
    }

    public function test_store_creates_lookup_request_and_dispatches_job(): void
    {
        $user = User::factory()->create();

        Queue::fake();

        $response = $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.store'), ['barcode' => '4901234567894']);

        $response->assertStatus(202)
            ->assertJsonStructure(['status', 'lookup_id'])
            ->assertJson(['status' => 'pending']);

        $this->assertDatabaseHas('food_lookup_requests', [
            'user_id' => $user->id,
            'barcode' => '4901234567894',
            'barcode_type' => 'ean13',
            'status' => FoodLookupStatus::Pending->value,
        ]);

        Queue::assertPushed(LookupOpenFoodFactsJob::class);
    }

    public function test_store_rejects_invalid_barcode(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.store'), ['barcode' => '12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('barcode');
    }

    public function test_store_rejects_bad_check_digit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.store'), ['barcode' => '4901234567890'])
            ->assertUnprocessable();
    }

    public function test_show_returns_pending_lookup_status(): void
    {
        $user = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($user)->create([
            'status' => FoodLookupStatus::Pending,
        ]);

        $this->actingAs($user)
            ->getJson(route('meals.barcode-lookup.show', $lookup->id))
            ->assertOk()
            ->assertJson(['status' => 'pending']);
    }

    public function test_show_returns_found_result(): void
    {
        $user = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($user)->found()->create();

        $response = $this->actingAs($user)
            ->getJson(route('meals.barcode-lookup.show', $lookup->id));

        $response->assertOk()
            ->assertJson([
                'status' => 'found',
                'source' => 'openfoodfacts',
            ])
            ->assertJsonStructure(['result' => ['name', 'kcal', 'protein_g', 'fat_g', 'carb_g']]);
    }

    public function test_show_returns_failed_status(): void
    {
        $user = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($user)->create([
            'status' => FoodLookupStatus::Failed,
            'error_code' => 'provider_error',
        ]);

        $this->actingAs($user)
            ->getJson(route('meals.barcode-lookup.show', $lookup->id))
            ->assertOk()
            ->assertJson([
                'status' => 'failed',
                'error_code' => 'provider_error',
            ]);
    }

    public function test_show_scoped_to_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($other)->create();

        $this->actingAs($user)
            ->getJson(route('meals.barcode-lookup.show', $lookup->id))
            ->assertNotFound();
    }

    public function test_confirm_saves_food_item(): void
    {
        $user = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($user)->found()->create();

        $response = $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.confirm', $lookup->id), [
                'name' => '確認済み食品',
                'serving_label' => '1個',
                'kcal' => 200,
                'protein_g' => 10,
                'fat_g' => 5,
                'carb_g' => 25,
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['food' => ['id', 'name']]);

        $this->assertDatabaseHas('food_items', [
            'user_id' => $user->id,
            'barcode' => '4901234567894',
            'name' => '確認済み食品',
            'kcal' => '200.00',
        ]);
    }

    public function test_confirm_rejects_non_found_lookup(): void
    {
        $user = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($user)->create([
            'status' => FoodLookupStatus::Pending,
        ]);

        $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.confirm', $lookup->id), [
                'name' => 'テスト',
                'serving_label' => '1個',
                'kcal' => 200,
                'protein_g' => 10,
                'fat_g' => 5,
                'carb_g' => 25,
            ])
            ->assertNotFound();
    }

    public function test_confirm_validates_nutrition_bounds(): void
    {
        $user = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($user)->found()->create();

        $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.confirm', $lookup->id), [
                'name' => '',
                'serving_label' => '',
                'kcal' => -1,
                'protein_g' => 1000,
                'fat_g' => 5,
                'carb_g' => 25,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'serving_label', 'kcal', 'protein_g']);
    }

    public function test_confirm_scoped_to_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($other)->found()->create();

        $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.confirm', $lookup->id), [
                'name' => 'テスト',
                'serving_label' => '1個',
                'kcal' => 200,
                'protein_g' => 10,
                'fat_g' => 5,
                'carb_g' => 25,
            ])
            ->assertNotFound();
    }

    public function test_confirm_restores_soft_deleted_food_with_same_barcode(): void
    {
        $user = User::factory()->create();
        $oldFood = FoodItem::factory()->for($user)->create([
            'barcode' => '4901234567894',
            'barcode_type' => 'ean13',
            'name' => '古い食品',
        ]);
        $oldFood->delete();

        $lookup = FoodLookupRequest::factory()->for($user)->found()->create();

        $response = $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.confirm', $lookup->id), [
                'name' => '新しい食品',
                'serving_label' => '1個',
                'kcal' => 300,
                'protein_g' => 15,
                'fat_g' => 8,
                'carb_g' => 35,
            ]);

        $response->assertCreated();

        $oldFood->refresh();
        $this->assertNull($oldFood->deleted_at);
        $this->assertSame('新しい食品', $oldFood->name);
    }

    public function test_store_reuses_pending_lookup_for_same_barcode(): void
    {
        $user = User::factory()->create();

        Queue::fake();

        $first = $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.store'), ['barcode' => '4901234567894']);

        $second = $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.store'), ['barcode' => '4901234567894']);

        $this->assertSame(
            $first->json('lookup_id'),
            $second->json('lookup_id'),
        );

        Queue::assertPushed(LookupOpenFoodFactsJob::class, 1);
    }

    public function test_upca_barcode_normalizes_to_ean13(): void
    {
        $user = User::factory()->create();

        Queue::fake();

        $response = $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.store'), ['barcode' => '036000291452']);

        $response->assertStatus(202);

        $this->assertDatabaseHas('food_lookup_requests', [
            'user_id' => $user->id,
            'barcode' => '0036000291452',
            'barcode_type' => 'upca',
        ]);
    }
}
