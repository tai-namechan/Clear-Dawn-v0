<?php

namespace Tests\Feature;

use App\Enums\FoodLookupStatus;
use App\Jobs\LookupOpenFoodFactsJob;
use App\Models\FoodLookupRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class LookupOpenFoodFactsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_updates_lookup_to_found_on_success(): void
    {
        $lookup = FoodLookupRequest::factory()->create([
            'barcode' => '4901234567894',
            'status' => FoodLookupStatus::Pending,
        ]);

        Http::fake([
            $this->openFoodFactsFakePattern() => Http::response([
                'product' => [
                    'product_name' => 'Test Cereal',
                    'brands' => 'Acme',
                    'serving_size' => '',
                    'nutriments' => [
                        'energy-kcal_100g' => 380,
                        'proteins_100g' => 8.5,
                        'fat_100g' => 2.1,
                        'carbohydrates_100g' => 75,
                    ],
                ],
            ], 200),
        ]);

        (new LookupOpenFoodFactsJob($lookup->id))->handle();

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Found, $lookup->status);
        $this->assertSame('openfoodfacts', $lookup->source);
        $this->assertSame('Test Cereal', $lookup->result['name']);
        $this->assertSame('100g', $lookup->result['per']);
        $this->assertEqualsWithDelta(380.0, (float) $lookup->result['kcal'], 0.001);
        $this->assertEqualsWithDelta(8.5, (float) $lookup->result['protein_g'], 0.001);
    }

    public function test_job_updates_lookup_to_not_found_on_404(): void
    {
        $lookup = FoodLookupRequest::factory()->create([
            'status' => FoodLookupStatus::Pending,
        ]);

        Http::fake([
            $this->openFoodFactsFakePattern() => Http::response(['status' => 0], 404),
        ]);

        (new LookupOpenFoodFactsJob($lookup->id))->handle();

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::NotFound, $lookup->status);
        $this->assertSame('openfoodfacts', $lookup->source);
        $this->assertNull($lookup->result);
    }

    public function test_job_marks_failed_after_retries_exhausted(): void
    {
        $lookup = FoodLookupRequest::factory()->create([
            'status' => FoodLookupStatus::Pending,
        ]);

        (new LookupOpenFoodFactsJob($lookup->id))->failed(new RuntimeException('provider down'));

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Failed, $lookup->status);
        $this->assertSame('openfoodfacts', $lookup->source);
        $this->assertSame('provider_error', $lookup->error_code);
    }

    public function test_job_skips_non_pending_lookup(): void
    {
        $lookup = FoodLookupRequest::factory()->found()->create();

        Http::fake([
            $this->openFoodFactsFakePattern() => Http::response(['product' => []], 200),
        ]);

        (new LookupOpenFoodFactsJob($lookup->id))->handle();

        Http::assertNothingSent();
        $this->assertSame(FoodLookupStatus::Found, $lookup->fresh()->status);
    }

    public function test_job_prefers_serving_over_100g(): void
    {
        $lookup = FoodLookupRequest::factory()->create([
            'status' => FoodLookupStatus::Pending,
        ]);

        Http::fake([
            $this->openFoodFactsFakePattern() => Http::response([
                'product' => [
                    'product_name' => 'Yogurt',
                    'serving_size' => '1 cup (245g)',
                    'nutriments' => [
                        'energy-kcal_serving' => 150,
                        'proteins_serving' => 12,
                        'fat_serving' => 4,
                        'carbohydrates_serving' => 17,
                        'energy-kcal_100g' => 61,
                        'proteins_100g' => 4.9,
                        'fat_100g' => 1.6,
                        'carbohydrates_100g' => 7,
                    ],
                ],
            ], 200),
        ]);

        (new LookupOpenFoodFactsJob($lookup->id))->handle();

        $result = $lookup->fresh()->result;
        $this->assertSame('serving', $result['per']);
        $this->assertSame('1 cup (245g)', $result['serving_label']);
        $this->assertEqualsWithDelta(150.0, (float) $result['kcal'], 0.001);
        $this->assertEqualsWithDelta(12.0, (float) $result['protein_g'], 0.001);
    }

    public function test_job_falls_back_to_100g_when_no_serving(): void
    {
        $lookup = FoodLookupRequest::factory()->create([
            'status' => FoodLookupStatus::Pending,
        ]);

        Http::fake([
            $this->openFoodFactsFakePattern() => Http::response([
                'product' => [
                    'product_name' => 'Rice',
                    'serving_size' => '',
                    'nutriments' => [
                        'energy-kcal_100g' => 360,
                        'proteins_100g' => 6.5,
                        'fat_100g' => 0.5,
                        'carbohydrates_100g' => 80,
                    ],
                ],
            ], 200),
        ]);

        (new LookupOpenFoodFactsJob($lookup->id))->handle();

        $result = $lookup->fresh()->result;
        $this->assertSame('100g', $result['per']);
        $this->assertSame('100g', $result['serving_label']);
        $this->assertEqualsWithDelta(360.0, (float) $result['kcal'], 0.001);
    }

    public function test_job_uses_japanese_product_name_first(): void
    {
        $lookup = FoodLookupRequest::factory()->create([
            'status' => FoodLookupStatus::Pending,
        ]);

        Http::fake([
            $this->openFoodFactsFakePattern() => Http::response([
                'product' => [
                    'product_name' => 'English Name',
                    'product_name_ja' => '日本語名',
                    'serving_size' => '',
                    'nutriments' => [
                        'energy-kcal_100g' => 100,
                        'proteins_100g' => 1,
                        'fat_100g' => 1,
                        'carbohydrates_100g' => 1,
                    ],
                ],
            ], 200),
        ]);

        (new LookupOpenFoodFactsJob($lookup->id))->handle();

        $this->assertSame('日本語名', $lookup->fresh()->result['name']);
        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/api/v2/product/')
                && $request->hasHeader('User-Agent', (string) config('services.openfoodfacts.user_agent'));
        });
    }

    /**
     * Build an Http::fake URL pattern from config (no hardcoded hosts).
     */
    private function openFoodFactsFakePattern(): string
    {
        $host = parse_url((string) config('services.openfoodfacts.base_url'), PHP_URL_HOST);

        return ($host ?: 'world.openfoodfacts.org').'/*';
    }
}
