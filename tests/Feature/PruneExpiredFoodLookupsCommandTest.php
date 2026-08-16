<?php

namespace Tests\Feature;

use App\Enums\FoodLookupStatus;
use App\Models\FoodItem;
use App\Models\FoodLookupRequest;
use App\Models\MealEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneExpiredFoodLookupsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_lookups_past_expires_at(): void
    {
        $expired = FoodLookupRequest::factory()->create([
            'expires_at' => now()->subMinute(),
        ]);

        Artisan::call('meals:prune-expired-lookups');

        $this->assertDatabaseMissing('food_lookup_requests', ['id' => $expired->id]);
    }

    public function test_keeps_lookups_not_yet_expired(): void
    {
        $active = FoodLookupRequest::factory()->create([
            'expires_at' => now()->addHour(),
        ]);

        Artisan::call('meals:prune-expired-lookups');

        $this->assertDatabaseHas('food_lookup_requests', ['id' => $active->id]);
    }

    public function test_deletes_temp_image_of_expired_lookup(): void
    {
        Storage::fake('food-label-ocr');
        config(['meals.label_ocr.disk' => 'food-label-ocr']);

        $expired = FoodLookupRequest::factory()->ocrPending()->create([
            'expires_at' => now()->subMinute(),
        ]);
        Storage::disk('food-label-ocr')->put((string) $expired->temp_image_path, 'stale-image');

        $active = FoodLookupRequest::factory()->ocrPending()->create([
            'expires_at' => now()->addHour(),
        ]);
        Storage::disk('food-label-ocr')->put((string) $active->temp_image_path, 'live-image');

        Artisan::call('meals:prune-expired-lookups');

        $this->assertDatabaseMissing('food_lookup_requests', ['id' => $expired->id]);
        Storage::disk('food-label-ocr')->assertMissing((string) $expired->temp_image_path);

        $this->assertDatabaseHas('food_lookup_requests', ['id' => $active->id]);
        Storage::disk('food-label-ocr')->assertExists((string) $active->temp_image_path);
    }

    public function test_deletes_expired_lookups_regardless_of_status(): void
    {
        $expiredFound = FoodLookupRequest::factory()->found()->create([
            'expires_at' => now()->subMinute(),
        ]);
        $expiredFailed = FoodLookupRequest::factory()->create([
            'status' => FoodLookupStatus::Failed,
            'error_code' => 'provider_error',
            'expires_at' => now()->subMinute(),
        ]);

        Artisan::call('meals:prune-expired-lookups');

        $this->assertDatabaseMissing('food_lookup_requests', ['id' => $expiredFound->id]);
        $this->assertDatabaseMissing('food_lookup_requests', ['id' => $expiredFailed->id]);
    }

    public function test_discards_label_ocr_meal_photos_and_keeps_photo_estimate(): void
    {
        Storage::fake('food-label-ocr');
        config(['meals.label_ocr.disk' => 'food-label-ocr']);

        $user = User::factory()->create();
        $labelFood = FoodItem::factory()->for($user)->create(['source' => 'label_ocr']);
        $photoFood = FoodItem::factory()->for($user)->create(['source' => 'ai_photo_estimate']);

        $labelEntry = MealEntry::factory()->for($user)->create(['food_item_id' => $labelFood->id]);
        $photoEntry = MealEntry::factory()->for($user)->create(['food_item_id' => $photoFood->id]);

        $labelPath = 'meal-photos/'.$user->id.'/'.$labelEntry->id.'.jpg';
        $photoPath = 'meal-photos/'.$user->id.'/'.$photoEntry->id.'.jpg';
        Storage::disk('food-label-ocr')->put($labelPath, 'label-bytes');
        Storage::disk('food-label-ocr')->put($photoPath, 'food-bytes');
        $labelEntry->forceFill(['photo_path' => $labelPath])->save();
        $photoEntry->forceFill(['photo_path' => $photoPath])->save();

        Artisan::call('meals:prune-expired-lookups');

        $this->assertNull($labelEntry->fresh()->photo_path);
        Storage::disk('food-label-ocr')->assertMissing($labelPath);

        $this->assertSame($photoPath, $photoEntry->fresh()->photo_path);
        Storage::disk('food-label-ocr')->assertExists($photoPath);
    }
}
