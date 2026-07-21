<?php

namespace Tests\Feature;

use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\AiMoney;
use App\Domain\Shared\AI\AiUsageLedger;
use App\Domain\Shared\Models\AiUsageRequest;
use App\Enums\AiUsageRequestStatus;
use App\Enums\FoodLookupStatus;
use App\Jobs\EstimateFoodMenuJob;
use App\Models\FoodItem;
use App\Models\FoodLookupRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class EstimateFoodMenuJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.anthropic.api_key' => 'test-key']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function menuLookup(array $overrides = []): FoodLookupRequest
    {
        return FoodLookupRequest::factory()->menuEstimate()->create($overrides);
    }

    private function fakeAiText(string $text): void
    {
        Http::fake([
            $this->anthropicFakePattern() => Http::response([
                'content' => [['type' => 'text', 'text' => $text]],
                'usage' => ['input_tokens' => 500, 'output_tokens' => 80],
            ], 200),
        ]);
    }

    private function runJob(FoodLookupRequest $lookup, int $attempts = 1): void
    {
        $job = new class($lookup->id, $attempts) extends EstimateFoodMenuJob
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

    public function test_job_marks_found_on_success(): void
    {
        $lookup = $this->menuLookup();

        $this->fakeAiText(json_encode([
            'name' => 'テストラーメン',
            'serving_label' => '1人前',
            'per' => 'serving',
            'kcal' => 750,
            'protein_g' => 30.0,
            'fat_g' => 35.0,
            'carb_g' => 70.0,
        ], JSON_UNESCAPED_UNICODE));

        $this->runJob($lookup);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Found, $lookup->status);
        $this->assertSame('ai_menu_estimate', $lookup->source);
        $this->assertSame('テストラーメン', $lookup->result['name']);
        $this->assertSame('serving', $lookup->result['per']);
        $this->assertEqualsWithDelta(750.0, (float) $lookup->result['kcal'], 0.001);
        $this->assertEqualsWithDelta(30.0, (float) $lookup->result['protein_g'], 0.001);

        $usage = AiUsageRequest::query()
            ->withoutUserScope()
            ->where('user_id', $lookup->user_id)
            ->where('feature', 'meals.menu_estimate')
            ->sole();
        $this->assertSame(AiUsageRequestStatus::Settled, $usage->status);

        $this->assertSame(0, FoodItem::query()->count());
    }

    public function test_job_sends_store_and_menu_in_user_message(): void
    {
        $lookup = $this->menuLookup([
            'meta' => ['store_name' => '一蘭', 'menu_name' => '天然とんこつラーメン'],
        ]);

        $this->fakeAiText((string) json_encode([
            'name' => '天然とんこつラーメン',
            'per' => 'serving', 'kcal' => 500, 'protein_g' => 20, 'fat_g' => 25, 'carb_g' => 50, 'serving_label' => '1人前',
        ]));

        $this->runJob($lookup);

        Http::assertSent(function (Request $request): bool {
            $messages = $request->data()['messages'] ?? [];
            $content = $messages[0]['content'] ?? '';

            return is_string($content)
                && str_contains($content, '一蘭')
                && str_contains($content, '天然とんこつラーメン');
        });
    }

    public function test_job_marks_unknown_menu_without_retry(): void
    {
        $lookup = $this->menuLookup();

        $this->fakeAiText('{"error":"unknown_menu"}');

        $this->runJob($lookup);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Failed, $lookup->status);
        $this->assertSame('menu_unknown', $lookup->error_code);
    }

    public function test_job_throws_for_retry_on_invalid_output_then_fails_terminally(): void
    {
        $lookup = $this->menuLookup();

        $this->fakeAiText('ここに栄養成分の情報はありません（JSONではない応答）');

        try {
            $this->runJob($lookup, attempts: 1);
            $this->fail('Expected retryable exception was not thrown.');
        } catch (RuntimeException) {
        }

        $this->assertSame(FoodLookupStatus::AiPending, $lookup->fresh()->status);

        $this->runJob($lookup, attempts: 2);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Failed, $lookup->status);
        $this->assertSame('menu_invalid_output', $lookup->error_code);
    }

    public function test_job_fails_terminally_on_invalid_meta(): void
    {
        $lookup = FoodLookupRequest::factory()->aiPending()->create([
            'meta' => ['store_name' => '', 'menu_name' => ''],
        ]);

        Http::fake();

        $this->runJob($lookup);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Failed, $lookup->status);
        $this->assertSame('menu_invalid_input', $lookup->error_code);
        Http::assertNothingSent();
    }

    public function test_job_fails_terminally_on_missing_meta(): void
    {
        $lookup = FoodLookupRequest::factory()->aiPending()->create([
            'meta' => null,
        ]);

        Http::fake();

        $this->runJob($lookup);

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Failed, $lookup->status);
        $this->assertSame('menu_invalid_input', $lookup->error_code);
        Http::assertNothingSent();
    }

    public function test_job_rejects_out_of_range_values_as_invalid(): void
    {
        $lookup = $this->menuLookup();

        $this->fakeAiText((string) json_encode([
            'name' => 'テスト',
            'per' => 'serving', 'kcal' => -100, 'protein_g' => 1, 'fat_g' => 1, 'carb_g' => 1, 'serving_label' => '1人前',
        ]));

        $this->runJob($lookup, attempts: 2);

        $this->assertSame('menu_invalid_output', $lookup->fresh()->error_code);
    }

    public function test_job_fails_terminally_on_quota_exceeded_without_http(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '0.000001']);

        $lookup = $this->menuLookup();

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
        $this->assertSame('menu_quota_exceeded', $lookup->error_code);
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

    public function test_failed_hook_marks_ai_pending_lookup_failed(): void
    {
        $lookup = $this->menuLookup();

        (new EstimateFoodMenuJob($lookup->id))->failed(new RuntimeException('worker killed'));

        $lookup->refresh();
        $this->assertSame(FoodLookupStatus::Failed, $lookup->status);
        $this->assertSame('menu_provider_error', $lookup->error_code);
    }
}
