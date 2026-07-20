<?php

namespace Tests\Feature;

use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\AiMoney;
use App\Domain\Shared\AI\AiUsageLedger;
use App\Domain\Shared\Models\AiUsageRequest;
use App\Enums\AiUsageRequestStatus;
use App\Enums\FoodLookupStatus;
use App\Jobs\LookupFoodLabelOcrJob;
use App\Models\FoodItem;
use App\Models\FoodLookupRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class LookupFoodLabelOcrJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('food-label-ocr');
        config([
            'meals.label_ocr.disk' => 'food-label-ocr',
            'ai.anthropic.api_key' => 'test-key',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function ocrLookup(array $overrides = []): FoodLookupRequest
    {
        $lookup = FoodLookupRequest::factory()->ocrPending()->create($overrides);
        Storage::disk('food-label-ocr')->put((string) $lookup->temp_image_path, 'fake-jpeg-bytes');

        return $lookup;
    }

    private function fakeAiText(string $text): void
    {
        Http::fake([
            $this->anthropicFakePattern() => Http::response([
                'content' => [['type' => 'text', 'text' => $text]],
                'usage' => ['input_tokens' => 1200, 'output_tokens' => 80],
            ], 200),
        ]);
    }

    private function runJob(FoodLookupRequest $lookup, int $attempts = 1): void
    {
        $job = new class($lookup->id, $attempts) extends LookupFoodLabelOcrJob
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

        $job->handle(app(AiGateway::class));
    }

    public function test_job_marks_found_and_deletes_image_on_success(): void
    {
        $lookup = $this->ocrLookup();
        $imagePath = (string) $lookup->temp_image_path;

        $this->fakeAiText(json_encode([
            'serving_label' => '1本(500ml)',
            'per' => 'serving',
            'kcal' => 225,
            'protein_g' => 7.5,
            'fat_g' => 12.4,
            'carb_g' => 22.1,
        ], JSON_UNESCAPED_UNICODE));

        $this->runJob($lookup);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Found, $lookup->status);
        $this->assertSame('label_ocr', $lookup->source);
        $this->assertNull($lookup->result['name']);
        $this->assertSame('serving', $lookup->result['per']);
        $this->assertSame('1本(500ml)', $lookup->result['serving_label']);
        $this->assertEqualsWithDelta(225.0, (float) $lookup->result['kcal'], 0.001);
        $this->assertEqualsWithDelta(12.4, (float) $lookup->result['fat_g'], 0.001);

        // 解析後破棄（完成設計 §3）
        $this->assertNull($lookup->temp_image_path);
        Storage::disk('food-label-ocr')->assertMissing($imagePath);

        // PR-A ledger へ自動連携され settle されている
        $usage = AiUsageRequest::query()
            ->withoutUserScope()
            ->where('user_id', $lookup->user_id)
            ->where('feature', 'meals.label_ocr')
            ->sole();
        $this->assertSame(AiUsageRequestStatus::Settled, $usage->status);

        // AI結果は自動確定しない: food_items には書かれない
        $this->assertSame(0, FoodItem::query()->count());
    }

    public function test_job_defaults_serving_label_for_100g(): void
    {
        $lookup = $this->ocrLookup();

        $this->fakeAiText((string) json_encode([
            'serving_label' => null,
            'per' => '100g',
            'kcal' => 52,
            'protein_g' => 0.2,
            'fat_g' => 0.1,
            'carb_g' => 13.8,
        ]));

        $this->runJob($lookup);

        $result = $lookup->fresh()->result;
        $this->assertSame('100g', $result['per']);
        $this->assertSame('100g', $result['serving_label']);
    }

    public function test_job_sends_image_as_base64_content_block(): void
    {
        $lookup = $this->ocrLookup();

        $this->fakeAiText((string) json_encode([
            'per' => '100g', 'kcal' => 1, 'protein_g' => 0, 'fat_g' => 0, 'carb_g' => 0, 'serving_label' => null,
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

    public function test_job_marks_unreadable_without_retry(): void
    {
        $lookup = $this->ocrLookup();
        $imagePath = (string) $lookup->temp_image_path;

        $this->fakeAiText('{"error":"unreadable"}');

        $this->runJob($lookup);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Failed, $lookup->status);
        $this->assertSame('ocr_unreadable', $lookup->error_code);
        Storage::disk('food-label-ocr')->assertMissing($imagePath);
    }

    public function test_job_throws_for_retry_on_invalid_output_then_fails_terminally(): void
    {
        $lookup = $this->ocrLookup();
        $imagePath = (string) $lookup->temp_image_path;

        $this->fakeAiText('栄養成分は読み取れませんでした（JSONではない応答）');

        // 1回目: リトライ余地があるので例外を投げ、状態は ocr_pending のまま
        try {
            $this->runJob($lookup, attempts: 1);
            $this->fail('Expected retryable exception was not thrown.');
        } catch (RuntimeException) {
        }

        $this->assertSame(FoodLookupStatus::OcrPending, $lookup->fresh()->status);
        Storage::disk('food-label-ocr')->assertExists($imagePath);

        // 最終試行: 終端 failed(ocr_invalid_output) + 画像破棄
        $this->runJob($lookup, attempts: 2);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Failed, $lookup->status);
        $this->assertSame('ocr_invalid_output', $lookup->error_code);
        Storage::disk('food-label-ocr')->assertMissing($imagePath);
    }

    public function test_job_rejects_out_of_range_values_as_invalid(): void
    {
        $lookup = $this->ocrLookup();

        $this->fakeAiText((string) json_encode([
            'per' => '100g', 'kcal' => -5, 'protein_g' => 1, 'fat_g' => 1, 'carb_g' => 1, 'serving_label' => null,
        ]));

        $this->runJob($lookup, attempts: 2);

        $this->assertSame('ocr_invalid_output', $lookup->fresh()->error_code);
    }

    public function test_job_fails_terminally_on_quota_exceeded_without_http(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '0.000001']);

        $lookup = $this->ocrLookup();
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
        $this->assertSame('ocr_quota_exceeded', $lookup->error_code);
        Storage::disk('food-label-ocr')->assertMissing($imagePath);
        Http::assertNothingSent();
    }

    public function test_job_skips_non_ocr_pending_lookup(): void
    {
        $lookup = FoodLookupRequest::factory()->found()->create();

        Http::fake();

        $this->runJob($lookup);

        Http::assertNothingSent();
        $this->assertSame(FoodLookupStatus::Found, $lookup->fresh()->status);
    }

    public function test_job_fails_terminally_when_image_is_missing(): void
    {
        $lookup = FoodLookupRequest::factory()->ocrPending()->create();
        // 画像ファイルを敢えて置かない

        Http::fake();

        $this->runJob($lookup);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Failed, $lookup->status);
        $this->assertSame('ocr_provider_error', $lookup->error_code);
        Http::assertNothingSent();
    }

    public function test_failed_hook_marks_ocr_pending_lookup_failed(): void
    {
        $lookup = $this->ocrLookup();
        $imagePath = (string) $lookup->temp_image_path;

        (new LookupFoodLabelOcrJob($lookup->id))->failed(new RuntimeException('worker killed'));

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Failed, $lookup->status);
        $this->assertSame('ocr_provider_error', $lookup->error_code);
        Storage::disk('food-label-ocr')->assertMissing($imagePath);
    }
}
