<?php

namespace Tests\Feature;

use App\Enums\FoodLookupStatus;
use App\Models\FoodLookupRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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
}
