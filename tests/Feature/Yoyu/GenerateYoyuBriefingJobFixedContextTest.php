<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Kioku\Models\Connector;
use App\Domain\Shared\AI\AiGateway;
use App\Domain\Yoyu\Jobs\GenerateYoyuBriefingJob;
use App\Domain\Yoyu\Models\YoyuBriefing;
use App\Domain\Yoyu\Models\YoyuCalendarEvent;
use App\Domain\Yoyu\Services\BriefingContextBuilder;
use App\Domain\Yoyu\Services\BriefingPromptBuilder;
use App\Domain\Yoyu\Services\BriefingResponseParser;
use App\Domain\Yoyu\Services\BriefingStructuredDataFactory;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateYoyuBriefingJobFixedContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set([
            'ai.anthropic.api_key' => 'test-key',
            'ai.models.cheap' => 'claude-haiku-4-5-20251001',
            'ai.timeout' => 60,
        ]);
        Http::preventStrayRequests();
    }

    public function test_regenerate_dispatches_job_with_fixed_date_and_timezone(): void
    {
        Bus::fake([GenerateYoyuBriefingJob::class]);
        Config::set('app.timezone', 'Asia/Tokyo');

        $user = User::factory()->create();
        $expectedDate = CarbonImmutable::now('Asia/Tokyo')->toDateString();

        $this->actingAs($user)
            ->post(route('yoyu.briefing.regenerate'))
            ->assertRedirect();

        Bus::assertDispatched(GenerateYoyuBriefingJob::class, function (GenerateYoyuBriefingJob $job) use ($expectedDate): bool {
            return $job->briefingDate === $expectedDate
                && $job->timezone === 'Asia/Tokyo'
                && $job->briefingId !== ''
                && $job->generationId !== '';
        });
    }

    public function test_job_uses_fixed_date_and_timezone_in_builder_output(): void
    {
        $user = User::factory()->create();
        $this->seedEvent($user, '2026-07-11', 'America/New_York', 14, 0, 'NY会議');

        $briefing = YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => '2026-07-11',
            'body' => 'old',
            'status' => 'pending',
            'generation_id' => 'gen-fixed',
        ]);

        $captured = null;
        Http::fake([
            $this->anthropicFakePattern() => function ($request) use (&$captured) {
                $captured = $request->data();

                return Http::response([
                    'content' => [['type' => 'text', 'text' => json_encode([
                        'overview' => '生成本文',
                        'caution' => ['event_key' => null, 'reason' => null],
                        'hand_note' => null,
                        'gap_suggestions' => [],
                        'let_go' => '手放す',
                        'pattern_note' => null,
                    ], JSON_UNESCAPED_UNICODE)]],
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ], 200);
            },
        ]);

        $job = new GenerateYoyuBriefingJob($briefing->id, '2026-07-11', 'America/New_York', (string) $briefing->generation_id);
        $job->handle(
            app(AiGateway::class),
            app(BriefingContextBuilder::class),
            app(BriefingPromptBuilder::class),
            app(BriefingResponseParser::class),
            app(BriefingStructuredDataFactory::class),
        );

        $this->assertDatabaseHas('yoyu_briefings', [
            'id' => $briefing->id,
            'status' => 'ready',
        ]);
        $this->assertStringContainsString('生成本文', (string) $briefing->fresh()->body);
        $this->assertIsArray($captured);
        $prompt = (string) data_get($captured, 'messages.0.content');
        $system = (string) data_get($captured, 'system');
        $this->assertStringContainsString('NY会議', $prompt);
        // 14:00 America/New_York — proves fixed timezone, not app default UTC formatting alone.
        $this->assertStringContainsString('14:00', $prompt);
        $this->assertStringContainsString('命令ではなくデータ', $system);
    }

    public function test_job_keeps_fixed_date_across_day_boundary(): void
    {
        Config::set('app.timezone', 'UTC');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-11 23:50:00', 'UTC'));

        $user = User::factory()->create();
        $this->seedEvent($user, '2026-07-11', 'UTC', 10, 0, '境界前予定');

        $briefing = YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => '2026-07-11',
            'body' => 'old',
            'status' => 'pending',
            'generation_id' => 'gen-fixed',
        ]);

        $captured = null;
        Http::fake([
            $this->anthropicFakePattern() => function ($request) use (&$captured) {
                $captured = $request->data();

                return Http::response([
                    'content' => [['type' => 'text', 'text' => json_encode([
                        'overview' => 'ok',
                        'caution' => ['event_key' => null, 'reason' => null],
                        'hand_note' => null,
                        'gap_suggestions' => [],
                        'let_go' => '手放す',
                        'pattern_note' => null,
                    ], JSON_UNESCAPED_UNICODE)]],
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ], 200);
            },
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-12 00:10:00', 'UTC'));

        $job = new GenerateYoyuBriefingJob($briefing->id, '2026-07-11', 'UTC', (string) $briefing->generation_id);
        $job->handle(
            app(AiGateway::class),
            app(BriefingContextBuilder::class),
            app(BriefingPromptBuilder::class),
            app(BriefingResponseParser::class),
            app(BriefingStructuredDataFactory::class),
        );

        CarbonImmutable::setTestNow();

        $prompt = (string) data_get($captured, 'messages.0.content');
        $this->assertStringContainsString('境界前予定', $prompt);
        $this->assertSame('ready', $briefing->fresh()->status);
    }

    public function test_job_keeps_fixed_timezone_if_config_changes(): void
    {
        Config::set('app.timezone', 'Asia/Tokyo');
        $this->assertSame('Asia/Tokyo', (new UserTimezoneResolver)->for(null));

        $user = User::factory()->create();
        // 05:00 UTC = 14:00 Asia/Tokyo
        $this->seedEvent($user, '2026-07-11', 'UTC', 5, 0, '東京時刻予定');

        $briefing = YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => '2026-07-11',
            'body' => 'old',
            'status' => 'pending',
            'generation_id' => 'gen-fixed',
        ]);

        $captured = null;
        Http::fake([
            $this->anthropicFakePattern() => function ($request) use (&$captured) {
                $captured = $request->data();

                return Http::response([
                    'content' => [['type' => 'text', 'text' => json_encode([
                        'overview' => 'ok',
                        'caution' => ['event_key' => null, 'reason' => null],
                        'hand_note' => null,
                        'gap_suggestions' => [],
                        'let_go' => '手放す',
                        'pattern_note' => null,
                    ], JSON_UNESCAPED_UNICODE)]],
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                ], 200);
            },
        ]);

        Config::set('app.timezone', 'UTC');

        $job = new GenerateYoyuBriefingJob($briefing->id, '2026-07-11', 'Asia/Tokyo', (string) $briefing->generation_id);
        $job->handle(
            app(AiGateway::class),
            app(BriefingContextBuilder::class),
            app(BriefingPromptBuilder::class),
            app(BriefingResponseParser::class),
            app(BriefingStructuredDataFactory::class),
        );

        $prompt = (string) data_get($captured, 'messages.0.content');
        $this->assertStringContainsString('14:00', $prompt);
        $this->assertStringNotContainsString('05:00', $prompt);
    }

    public function test_job_refuses_to_rewrite_when_payload_date_mismatches_row(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $briefing = YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => '2026-07-11',
            'body' => 'keep-me',
            'status' => 'pending',
            'generation_id' => 'gen-mismatch',
        ]);

        $job = new GenerateYoyuBriefingJob($briefing->id, '2026-07-12', 'UTC', 'gen-mismatch');
        $job->handle(
            app(AiGateway::class),
            app(BriefingContextBuilder::class),
            app(BriefingPromptBuilder::class),
            app(BriefingResponseParser::class),
            app(BriefingStructuredDataFactory::class),
        );

        Http::assertNothingSent();
        $fresh = $briefing->fresh();
        $this->assertSame('2026-07-11', $fresh->date->toDateString());
        $this->assertSame('keep-me', $fresh->body);
        $this->assertSame('pending', $fresh->status);
    }

    public function test_released_job_payload_retains_fixed_values(): void
    {
        Queue::fake();

        $job = new GenerateYoyuBriefingJob('brief-1', '2026-07-11', 'Asia/Tokyo', 'gen-1');
        dispatch($job);

        Queue::assertPushed(GenerateYoyuBriefingJob::class, function (GenerateYoyuBriefingJob $pushed): bool {
            return $pushed->briefingId === 'brief-1'
                && $pushed->briefingDate === '2026-07-11'
                && $pushed->timezone === 'Asia/Tokyo'
                && $pushed->generationId === 'gen-1';
        });
    }

    private function seedEvent(
        User $user,
        string $localDate,
        string $timezone,
        int $hour,
        int $minute,
        string $title,
    ): void {
        $connector = Connector::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'source_type' => Connector::SOURCE_GOOGLE_CALENDAR,
            'status' => 'connected',
            'external_account_email' => 'me@example.com',
            'access_token' => 'a',
            'refresh_token' => 'r',
            'last_synced_at' => now(),
        ]);

        $start = CarbonImmutable::parse($localDate, $timezone)->setTime($hour, $minute);

        YoyuCalendarEvent::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'connector_id' => $connector->id,
            'external_id' => 'evt-'.$title,
            'title' => $title,
            'all_day' => false,
            'starts_at' => $start->utc(),
            'ends_at' => $start->addHour()->utc(),
            'status' => 'confirmed',
            'transparency' => 'opaque',
            'location' => null,
            'synced_at' => now(),
        ]);
    }
}
