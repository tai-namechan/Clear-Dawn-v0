<?php

namespace Tests\Feature;

use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\AiMoney;
use App\Domain\Shared\AI\AiUsageLedger;
use App\Domain\Shared\Models\AiUsageRequest;
use App\Enums\AiUsageRequestStatus;
use App\Enums\FoodLookupStatus;
use App\Jobs\EstimateFoodPhotoJob;
use App\Models\FoodItem;
use App\Models\FoodLookupRequest;
use App\Services\ChainNutritionScraper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class EstimateFoodPhotoJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('food-label-ocr');
        Http::preventStrayRequests();
        config([
            'meals.label_ocr.disk' => 'food-label-ocr',
            'ai.anthropic.api_key' => 'test-key',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function photoLookup(array $overrides = []): FoodLookupRequest
    {
        $lookup = FoodLookupRequest::factory()->photoEstimate()->create($overrides);
        Storage::disk('food-label-ocr')->put((string) $lookup->temp_image_path, 'fake-jpeg-bytes');

        return $lookup;
    }

    private function fakeAiText(string $text): void
    {
        Http::fake([
            'www.fatsecret.jp/*' => Http::response('', 404),
            $this->anthropicFakePattern() => Http::response([
                'content' => [['type' => 'text', 'text' => $text]],
                'usage' => ['input_tokens' => 1200, 'output_tokens' => 80],
            ], 200),
        ]);
    }

    private function runJob(FoodLookupRequest $lookup, int $attempts = 1): void
    {
        $job = new class($lookup->id, $attempts) extends EstimateFoodPhotoJob
        {
            public function __construct(string $lookupRequestId, private readonly int $fakeAttempts)
            {
                parent::__construct($lookupRequestId);
            }

            public function attempts(): int
            {
                return $this->fakeAttempts;
            }
        };

        $job->handle(app(AiGateway::class), app(ChainNutritionScraper::class));
    }

    public function test_job_marks_found_and_deletes_image_on_success(): void
    {
        $lookup = $this->photoLookup();
        $imagePath = (string) $lookup->temp_image_path;

        $this->fakeAiText(json_encode([
            'name' => 'チキンカレー',
            'serving_label' => '1皿',
            'per' => 'serving',
            'kcal' => 680,
            'protein_g' => 25.0,
            'fat_g' => 28.0,
            'carb_g' => 80.0,
        ], JSON_UNESCAPED_UNICODE));

        $this->runJob($lookup);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Found, $lookup->status);
        $this->assertSame('ai_photo_estimate', $lookup->source);
        $this->assertSame('チキンカレー', $lookup->result['name']);
        $this->assertSame('serving', $lookup->result['per']);
        $this->assertSame('1皿', $lookup->result['serving_label']);
        $this->assertEqualsWithDelta(680.0, (float) $lookup->result['kcal'], 0.001);
        $this->assertEqualsWithDelta(25.0, (float) $lookup->result['protein_g'], 0.001);

        $this->assertNull($lookup->temp_image_path);
        Storage::disk('food-label-ocr')->assertMissing($imagePath);

        $usage = AiUsageRequest::query()
            ->withoutUserScope()
            ->where('user_id', $lookup->user_id)
            ->where('feature', 'meals.photo_estimate')
            ->sole();
        $this->assertSame(AiUsageRequestStatus::Settled, $usage->status);

        $this->assertSame(0, FoodItem::query()->count());
    }

    public function test_job_sends_image_as_base64_content_block(): void
    {
        $lookup = $this->photoLookup();

        $this->fakeAiText((string) json_encode([
            'name' => 'テスト',
            'per' => 'serving', 'kcal' => 1, 'protein_g' => 0, 'fat_g' => 0, 'carb_g' => 0, 'serving_label' => '1人前',
        ]));

        $this->runJob($lookup);

        Http::assertSent(function (Request $request): bool {
            $messages = $request->data()['messages'] ?? [];
            $content = $messages[0]['content'] ?? [];

            return is_array($content)
                && ($content[0]['type'] ?? null) === 'image'
                && ($content[0]['source']['type'] ?? null) === 'base64'
                && ($content[0]['source']['data'] ?? null) === base64_encode('fake-jpeg-bytes');
        });
    }

    public function test_job_marks_unrecognizable_without_retry(): void
    {
        $lookup = $this->photoLookup();
        $imagePath = (string) $lookup->temp_image_path;

        $this->fakeAiText('{"error":"unrecognizable"}');

        $this->runJob($lookup);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Failed, $lookup->status);
        $this->assertSame('photo_unrecognizable', $lookup->error_code);
        Storage::disk('food-label-ocr')->assertMissing($imagePath);
    }

    public function test_job_throws_for_retry_on_invalid_output_then_fails_terminally(): void
    {
        $lookup = $this->photoLookup();
        $imagePath = (string) $lookup->temp_image_path;

        $this->fakeAiText('これは料理の写真ではありません（JSONではない応答）');

        try {
            $this->runJob($lookup, attempts: 1);
            $this->fail('Expected retryable exception was not thrown.');
        } catch (RuntimeException) {
        }

        $this->assertSame(FoodLookupStatus::AiPending, $lookup->fresh()->status);
        Storage::disk('food-label-ocr')->assertExists($imagePath);

        $this->runJob($lookup, attempts: 2);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Failed, $lookup->status);
        $this->assertSame('photo_invalid_output', $lookup->error_code);
        Storage::disk('food-label-ocr')->assertMissing($imagePath);
    }

    public function test_job_rejects_out_of_range_values_as_invalid(): void
    {
        $lookup = $this->photoLookup();

        $this->fakeAiText((string) json_encode([
            'name' => 'テスト',
            'per' => 'serving', 'kcal' => -5, 'protein_g' => 1, 'fat_g' => 1, 'carb_g' => 1, 'serving_label' => '1人前',
        ]));

        $this->runJob($lookup, attempts: 2);

        $this->assertSame('photo_invalid_output', $lookup->fresh()->error_code);
    }

    public function test_job_fails_terminally_on_quota_exceeded_without_http(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '0.000001']);

        $lookup = $this->photoLookup();
        $imagePath = (string) $lookup->temp_image_path;

        app(AiUsageLedger::class)->reserve(
            (int) $lookup->user_id,
            'fill',
            'claude-haiku-4-5-20251001',
            AiMoney::of('0.000001'),
        );

        Http::fake();

        $this->runJob($lookup);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Failed, $lookup->status);
        $this->assertSame('photo_quota_exceeded', $lookup->error_code);
        Storage::disk('food-label-ocr')->assertMissing($imagePath);
        Http::assertNothingSent();
    }

    public function test_job_skips_non_ai_pending_lookup(): void
    {
        $lookup = FoodLookupRequest::factory()->found()->create();

        Http::fake();

        $this->runJob($lookup);

        Http::assertNothingSent();
        $this->assertSame(FoodLookupStatus::Found, $lookup->fresh()->status);
    }

    public function test_job_fails_terminally_when_image_is_missing(): void
    {
        $lookup = FoodLookupRequest::factory()->photoEstimate()->create();

        Http::fake();

        $this->runJob($lookup);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Failed, $lookup->status);
        $this->assertSame('photo_provider_error', $lookup->error_code);
        Http::assertNothingSent();
    }

    public function test_failed_hook_marks_ai_pending_lookup_failed(): void
    {
        $lookup = $this->photoLookup();
        $imagePath = (string) $lookup->temp_image_path;

        (new EstimateFoodPhotoJob($lookup->id))->failed(new RuntimeException('worker killed'));

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Failed, $lookup->status);
        $this->assertSame('photo_provider_error', $lookup->error_code);
        Storage::disk('food-label-ocr')->assertMissing($imagePath);
    }

    public function test_job_defaults_serving_label_when_missing(): void
    {
        $lookup = $this->photoLookup();

        $this->fakeAiText((string) json_encode([
            'name' => 'ラーメン',
            'serving_label' => null,
            'per' => 'serving',
            'kcal' => 500,
            'protein_g' => 20,
            'fat_g' => 15,
            'carb_g' => 60,
        ]));

        $this->runJob($lookup);

        $result = $lookup->fresh()->result;
        $this->assertSame('1人前', $result['serving_label']);
    }

    public function test_store_name_extraction_triggers_scraper_and_uses_nutrition_db(): void
    {
        $lookup = $this->photoLookup();

        $detailHtml = <<<'HTML'
        <html><body>
        <h1>すき家 牛丼</h1>
        <div>1食分（350g）</div>
        <div>カロリー: 733 kcal</div>
        <div>たんぱく質: 22.0 g</div>
        <div>脂質: 25.0 g</div>
        <div>炭水化物: 104.0 g</div>
        </body></html>
        HTML;

        Http::fake([
            'www.fatsecret.jp/*' => Http::sequence()
                ->push('<a href="https://www.fatsecret.jp/カロリー-栄養/すき家/牛丼/1食" class="prominent">牛丼</a>', 200)
                ->push($detailHtml, 200),
            $this->anthropicFakePattern() => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'name' => '牛丼',
                    'serving_label' => '並盛',
                    'per' => 'serving',
                    'kcal' => 700,
                    'protein_g' => 20,
                    'fat_g' => 24,
                    'carb_g' => 100,
                    'store_name' => 'すき家',
                    'menu_name' => '牛丼',
                ], JSON_UNESCAPED_UNICODE)]],
                'usage' => ['input_tokens' => 1200, 'output_tokens' => 80],
            ], 200),
        ]);

        $this->runJob($lookup);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Found, $lookup->status);
        $this->assertSame('nutrition_db', $lookup->source);
        $this->assertEqualsWithDelta(733.0, (float) $lookup->result['kcal'], 0.01);

        $meta = $lookup->meta;
        $this->assertSame('すき家', $meta['store_name']);
        $this->assertSame('牛丼', $meta['menu_name']);
    }

    public function test_store_name_extraction_falls_back_to_ai_when_scraper_misses(): void
    {
        $lookup = $this->photoLookup();

        Http::fake([
            'www.fatsecret.jp/*' => Http::response('', 404),
            $this->anthropicFakePattern() => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'name' => '味噌ラーメン',
                    'serving_label' => '1杯',
                    'per' => 'serving',
                    'kcal' => 950,
                    'protein_g' => 30,
                    'fat_g' => 40,
                    'carb_g' => 100,
                    'store_name' => '個人店ラーメン屋',
                    'menu_name' => '味噌ラーメン',
                ], JSON_UNESCAPED_UNICODE)]],
                'usage' => ['input_tokens' => 1200, 'output_tokens' => 80],
            ], 200),
        ]);

        $this->runJob($lookup);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Found, $lookup->status);
        $this->assertSame('ai_photo_estimate', $lookup->source);
        $this->assertEqualsWithDelta(950.0, (float) $lookup->result['kcal'], 0.01);

        $meta = $lookup->meta;
        $this->assertSame('個人店ラーメン屋', $meta['store_name']);
        $this->assertSame('味噌ラーメン', $meta['menu_name']);
    }

    public function test_no_store_name_skips_scraper(): void
    {
        $lookup = $this->photoLookup();

        $this->fakeAiText(json_encode([
            'name' => 'カレーライス',
            'serving_label' => '1皿',
            'per' => 'serving',
            'kcal' => 680,
            'protein_g' => 25,
            'fat_g' => 28,
            'carb_g' => 80,
            'store_name' => null,
            'menu_name' => null,
        ], JSON_UNESCAPED_UNICODE));

        $this->runJob($lookup);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Found, $lookup->status);
        $this->assertSame('ai_photo_estimate', $lookup->source);

        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'fatsecret'));
    }
}
