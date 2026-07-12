<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\MemoryClassifier;
use App\Domain\Kioku\Services\RelatedMemoryService;
use App\Domain\Kioku\Types\MemoryTypeRegistry;
use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class MemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_kioku_home(): void
    {
        $this->get(route('kioku.home'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_kioku_index(): void
    {
        $user = User::factory()->create();
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '自分の記憶',
            'status' => 'ready',
        ]);
        Memory::factory()->create([
            'user_id' => User::factory()->create()->id,
            'title' => '他人の記憶',
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->get(route('kioku.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kioku/Index')
                ->has('memories', 1)
                ->where('memories.0.title', '自分の記憶')
                ->where('currentProduct', 'kioku')
                ->has('typeCounts')
                ->has('sourceCounts')
                ->where('totalCount', 1)
            );
    }

    public function test_store_saves_raw_content_immediately_and_dispatches_enrichment(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('kioku.memories.store'), [
                'raw_content' => 'Vite manifest not found エラーが出た',
            ])
            ->assertRedirect(route('kioku.home'));

        $memory = Memory::query()->withoutUserScope()->where('user_id', $user->id)->first();
        $this->assertNotNull($memory);
        $this->assertSame('Vite manifest not found エラーが出た', $memory->raw_content);
        $this->assertSame('captured', $memory->status);
        $this->assertSame('整理中…', $memory->title);

        Bus::assertDispatched(EnrichMemoryJob::class);
    }

    public function test_store_responds_without_running_ai_and_queues_job(): void
    {
        config(['queue.default' => 'database']);
        Http::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('kioku.memories.store'), [
                'raw_content' => 'AIを待たずに応答すべき',
            ])
            ->assertRedirect(route('kioku.home'));

        Http::assertNothingSent();
        $this->assertDatabaseCount('jobs', 1);

        $memory = Memory::query()->withoutUserScope()->where('user_id', $user->id)->first();
        $this->assertNotNull($memory);
        $this->assertSame('captured', $memory->status);
    }

    public function test_queue_worker_enriches_memory_to_ready(): void
    {
        config(['queue.default' => 'database']);
        Http::fake([
            $this->anthropicFakePattern() => Http::sequence()
                ->push([
                    'content' => [[
                        'type' => 'text',
                        'text' => '{"memory_type":"thought","importance":3,"tags":["メモ"],"title":"考えごと"}',
                    ]],
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
                ])
                ->push([
                    'content' => [[
                        'type' => 'text',
                        'text' => '{"summary":"要約","structured_data":null}',
                    ]],
                    'usage' => ['input_tokens' => 11, 'output_tokens' => 30],
                ]),
        ]);
        config(['ai.anthropic.api_key' => 'test-key']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('kioku.memories.store'), [
                'raw_content' => 'Worker経由で整理される',
            ])
            ->assertRedirect(route('kioku.home'));

        $this->assertDatabaseCount('jobs', 1);

        $this->artisan('queue:work', ['--once' => true, '--sleep' => 0])->assertSuccessful();

        $memory = Memory::query()->withoutUserScope()->where('user_id', $user->id)->first();
        $this->assertNotNull($memory);
        $this->assertSame('ready', $memory->status);
        $this->assertSame('thought', $memory->memory_type);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_duplicate_dispatch_queues_only_one_job(): void
    {
        config(['queue.default' => 'database']);

        $user = User::factory()->create();
        $memory = Memory::factory()->captured()->create(['user_id' => $user->id]);

        EnrichMemoryJob::dispatch($memory->id);
        EnrichMemoryJob::dispatch($memory->id);

        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_ready_memory_is_not_reprocessed(): void
    {
        Http::fake();
        config(['ai.anthropic.api_key' => 'test-key']);

        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'memory_type' => 'thought',
        ]);

        (new EnrichMemoryJob($memory->id))->handle(
            app(AiGateway::class),
            app(MemoryTypeRegistry::class),
            app(MemoryClassifier::class),
            app(RelatedMemoryService::class),
        );

        Http::assertNothingSent();
        $this->assertSame('ready', $memory->fresh()->status);
    }

    public function test_persisted_classification_is_not_rebilled_on_retry(): void
    {
        Http::fake([
            $this->anthropicFakePattern() => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => '{"summary":"再試行の要約","structured_data":null}',
                ]],
                'usage' => ['input_tokens' => 5, 'output_tokens' => 10],
            ]),
        ]);
        config(['ai.anthropic.api_key' => 'test-key']);

        $user = User::factory()->create();
        $memory = Memory::factory()->captured()->create([
            'user_id' => $user->id,
            'memory_type' => 'thought',
            'title' => '前回のclassify結果',
        ]);

        (new EnrichMemoryJob($memory->id))->handle(
            app(AiGateway::class),
            app(MemoryTypeRegistry::class),
            app(MemoryClassifier::class),
            app(RelatedMemoryService::class),
        );

        Http::assertSentCount(1);
        $memory->refresh();
        $this->assertSame('ready', $memory->status);
        $this->assertSame('前回のclassify結果', $memory->title);
        $this->assertSame(1, AiUsageLog::query()->withoutUserScope()->where('user_id', $user->id)->count());
    }

    public function test_owner_can_reenrich_ready_memory(): void
    {
        Bus::fake([EnrichMemoryJob::class]);

        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'memory_type' => 'error_log',
            'summary' => '古い要約',
            'tags' => ['古いタグ'],
        ]);

        $this->actingAs($user)
            ->post(route('kioku.memories.reenrich', $memory))
            ->assertRedirect(route('kioku.memories.show', $memory));

        $memory->refresh();
        $this->assertSame('captured', $memory->status);
        $this->assertNull($memory->memory_type);
        $this->assertNull($memory->summary);
        Bus::assertDispatched(EnrichMemoryJob::class);
    }

    public function test_reenrich_does_not_reset_in_flight_memory(): void
    {
        Bus::fake([EnrichMemoryJob::class]);

        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'enriching',
        ]);

        $this->actingAs($user)
            ->post(route('kioku.memories.reenrich', $memory))
            ->assertRedirect(route('kioku.memories.show', $memory));

        $this->assertSame('enriching', $memory->fresh()->status);
        Bus::assertNotDispatched(EnrichMemoryJob::class);
    }

    public function test_user_cannot_reenrich_another_users_memory(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->post(route('kioku.memories.reenrich', $memory))
            ->assertNotFound();
    }

    public function test_classify_eval_command_runs_against_fixture(): void
    {
        Http::fake([
            $this->anthropicFakePattern() => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => '{"memory_type":"thought","importance":3,"tags":["メモ"],"title":"テストのメモ"}',
                ]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
            ]),
        ]);
        config(['ai.anthropic.api_key' => 'test-key']);

        User::factory()->create();

        $this->artisan('kioku:eval-classify', [
            '--fixture' => 'tests/Fixtures/kioku-classify-eval-smoke.json',
            '--yes' => true,
        ])
            ->expectsOutputToContain('合格: 1/1')
            ->assertSuccessful();
    }

    public function test_job_is_queued_only_after_transaction_commit(): void
    {
        config(['queue.default' => 'database']);

        $user = User::factory()->create();
        $memory = Memory::factory()->captured()->create(['user_id' => $user->id]);

        DB::transaction(function () use ($memory): void {
            EnrichMemoryJob::dispatch($memory->id)->afterCommit();

            $this->assertDatabaseCount('jobs', 0);
        });

        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_job_is_not_queued_when_transaction_rolls_back(): void
    {
        config(['queue.default' => 'database']);

        $user = User::factory()->create();
        $memory = Memory::factory()->captured()->create(['user_id' => $user->id]);

        try {
            DB::transaction(function () use ($memory): void {
                EnrichMemoryJob::dispatch($memory->id)->afterCommit();

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_search_filters_by_keyword_and_excludes_other_users(): void
    {
        $user = User::factory()->create();
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'Vite エラー',
            'raw_content' => 'manifest missing',
            'summary' => 'build 忘れ',
            'status' => 'ready',
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '転職メモ',
            'raw_content' => 'ポートフォリオ',
            'status' => 'ready',
        ]);
        Memory::factory()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Vite 他人',
            'raw_content' => 'Vite',
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->get(route('kioku.home', ['q' => 'Vite']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('memories', 1)
                ->where('memories.0.title', 'Vite エラー')
            );
    }

    public function test_enrichment_failure_keeps_raw_content(): void
    {
        Http::fake([
            $this->anthropicFakePattern() => Http::response(['error' => 'unauthorized'], 401),
        ]);
        config(['ai.anthropic.api_key' => 'test-key']);

        $user = User::factory()->create();
        $memory = Memory::factory()->captured()->create([
            'user_id' => $user->id,
            'raw_content' => '原文は残るべき',
        ]);

        $job = new EnrichMemoryJob($memory->id);
        $job->tries = 1;
        $job->handle(
            app(AiGateway::class),
            app(MemoryTypeRegistry::class),
            app(MemoryClassifier::class),
            app(RelatedMemoryService::class),
        );

        $memory->refresh();
        $this->assertSame('原文は残るべき', $memory->raw_content);
        $this->assertSame('failed', $memory->status);
    }

    public function test_successful_enrichment_writes_usage_log(): void
    {
        Http::fake([
            $this->anthropicFakePattern() => Http::sequence()
                ->push([
                    'content' => [[
                        'type' => 'text',
                        'text' => '{"memory_type":"error_log","importance":4,"tags":["Vite"],"title":"Viteエラー"}',
                    ]],
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
                ])
                ->push([
                    'content' => [[
                        'type' => 'text',
                        'text' => '{"summary":"manifest欠落","structured_data":{"error_message":"manifest","environment":"local","cause":"build","solution":["npm run build"],"related_files":[],"resolved":true}}',
                    ]],
                    'usage' => ['input_tokens' => 11, 'output_tokens' => 30],
                ]),
        ]);
        config(['ai.anthropic.api_key' => 'test-key']);

        $user = User::factory()->create();
        $memory = Memory::factory()->captured()->create([
            'user_id' => $user->id,
            'raw_content' => 'Vite manifest not found',
        ]);

        (new EnrichMemoryJob($memory->id))->handle(
            app(AiGateway::class),
            app(MemoryTypeRegistry::class),
            app(MemoryClassifier::class),
            app(RelatedMemoryService::class),
        );

        $memory->refresh();
        $this->assertSame('ready', $memory->status);
        $this->assertSame('error_log', $memory->memory_type);
        $this->assertSame('manifest欠落', $memory->summary);
        $this->assertSame(2, AiUsageLog::query()->withoutUserScope()->where('user_id', $user->id)->count());
    }
}
