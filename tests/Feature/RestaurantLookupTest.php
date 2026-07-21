<?php

namespace Tests\Feature;

use App\Domain\Shared\AI\AiMoney;
use App\Domain\Shared\AI\AiUsageLedger;
use App\Enums\FoodLookupStatus;
use App\Jobs\EstimateFoodMenuJob;
use App\Jobs\EstimateFoodPhotoJob;
use App\Models\FoodLookupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestaurantLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('food-label-ocr');
        config(['meals.label_ocr.disk' => 'food-label-ocr']);
    }

    public function test_guests_cannot_access_photo_estimate(): void
    {
        $this->postJson(route('meals.photo-estimate.store'))
            ->assertUnauthorized();
    }

    public function test_guests_cannot_access_menu_estimate(): void
    {
        $this->postJson(route('meals.menu-estimate.store'))
            ->assertUnauthorized();
    }

    public function test_photo_estimate_creates_lookup_and_dispatches_job(): void
    {
        $user = User::factory()->create();

        Queue::fake();

        $response = $this->actingAs($user)
            ->postJson(route('meals.photo-estimate.store'), [
                'image' => UploadedFile::fake()->image('food.jpg', 400, 400),
            ]);

        $response->assertStatus(202)
            ->assertJsonStructure(['status', 'lookup_id'])
            ->assertJson(['status' => 'ai_pending']);

        $this->assertDatabaseHas('food_lookup_requests', [
            'user_id' => $user->id,
            'status' => FoodLookupStatus::AiPending->value,
            'barcode' => null,
        ]);

        Queue::assertPushed(EstimateFoodPhotoJob::class);
    }

    public function test_photo_estimate_validates_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('meals.photo-estimate.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_photo_estimate_rejects_non_image_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('meals.photo-estimate.store'), [
                'image' => UploadedFile::fake()->create('document.pdf', 100),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_photo_estimate_rejects_too_small_image(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('meals.photo-estimate.store'), [
                'image' => UploadedFile::fake()->image('tiny.jpg', 100, 100),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_menu_estimate_creates_lookup_and_dispatches_job(): void
    {
        $user = User::factory()->create();

        Queue::fake();

        $response = $this->actingAs($user)
            ->postJson(route('meals.menu-estimate.store'), [
                'store_name' => '一蘭',
                'menu_name' => '天然とんこつラーメン',
            ]);

        $response->assertStatus(202)
            ->assertJsonStructure(['status', 'lookup_id'])
            ->assertJson(['status' => 'ai_pending']);

        $this->assertDatabaseHas('food_lookup_requests', [
            'user_id' => $user->id,
            'status' => FoodLookupStatus::AiPending->value,
            'barcode' => null,
        ]);

        $lookup = FoodLookupRequest::query()
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        $this->assertSame('一蘭', $lookup->meta['store_name']);
        $this->assertSame('天然とんこつラーメン', $lookup->meta['menu_name']);

        Queue::assertPushed(EstimateFoodMenuJob::class);
    }

    public function test_menu_estimate_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('meals.menu-estimate.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['store_name', 'menu_name']);
    }

    public function test_menu_estimate_validates_max_length(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('meals.menu-estimate.store'), [
                'store_name' => str_repeat('あ', 101),
                'menu_name' => str_repeat('い', 101),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['store_name', 'menu_name']);
    }

    public function test_show_returns_ai_pending_status(): void
    {
        $user = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($user)->aiPending()->create();

        $this->actingAs($user)
            ->getJson(route('meals.barcode-lookup.show', $lookup->id))
            ->assertOk()
            ->assertJson(['status' => 'ai_pending']);
    }

    public function test_show_returns_found_result_for_ai_estimate(): void
    {
        $user = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($user)->found()->create([
            'source' => 'ai_photo_estimate',
            'barcode' => null,
            'barcode_type' => null,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('meals.barcode-lookup.show', $lookup->id));

        $response->assertOk()
            ->assertJson([
                'status' => 'found',
                'source' => 'ai_photo_estimate',
            ])
            ->assertJsonStructure(['result' => ['name', 'kcal', 'protein_g', 'fat_g', 'carb_g']]);
    }

    public function test_show_returns_failed_with_error_code_for_ai_estimate(): void
    {
        $user = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($user)->create([
            'status' => FoodLookupStatus::Failed,
            'error_code' => 'photo_unrecognizable',
            'barcode' => null,
            'barcode_type' => null,
        ]);

        $this->actingAs($user)
            ->getJson(route('meals.barcode-lookup.show', $lookup->id))
            ->assertOk()
            ->assertJson([
                'status' => 'failed',
                'error_code' => 'photo_unrecognizable',
            ]);
    }

    public function test_confirm_works_for_ai_estimated_lookup(): void
    {
        $user = User::factory()->create();
        $lookup = FoodLookupRequest::factory()->for($user)->found()->create([
            'source' => 'ai_photo_estimate',
            'barcode' => null,
            'barcode_type' => null,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('meals.barcode-lookup.confirm', $lookup->id), [
                'name' => 'チキンカレー',
                'serving_label' => '1皿',
                'kcal' => 680,
                'protein_g' => 25,
                'fat_g' => 28,
                'carb_g' => 80,
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['food' => ['id', 'name']]);

        $this->assertDatabaseHas('food_items', [
            'user_id' => $user->id,
            'name' => 'チキンカレー',
            'barcode' => null,
        ]);
    }

    public function test_photo_estimate_returns_quota_error(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '0.000001']);

        $user = User::factory()->create();

        app(AiUsageLedger::class)->reserve(
            $user->id,
            'fill',
            'claude-haiku-4-5-20251001',
            AiMoney::of('0.000001'),
        );

        $this->actingAs($user)
            ->postJson(route('meals.photo-estimate.store'), [
                'image' => UploadedFile::fake()->image('food.jpg', 400, 400),
            ])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => '今月のAI利用枠を使い切りました。来月また利用できます。']);
    }

    public function test_menu_estimate_returns_quota_error(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '0.000001']);

        $user = User::factory()->create();

        app(AiUsageLedger::class)->reserve(
            $user->id,
            'fill',
            'claude-haiku-4-5-20251001',
            AiMoney::of('0.000001'),
        );

        $this->actingAs($user)
            ->postJson(route('meals.menu-estimate.store'), [
                'store_name' => '松屋',
                'menu_name' => '牛めし並盛',
            ])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => '今月のAI利用枠を使い切りました。来月また利用できます。']);
    }
}
