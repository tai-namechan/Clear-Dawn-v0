<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\RelatedMemoryService;
use App\Domain\Kioku\Types\MemoryTypeRegistry;
use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
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
        $this->assertSame('enriching', $memory->status);
        $this->assertSame('整理中…', $memory->title);

        Bus::assertDispatched(EnrichMemoryJob::class);
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
            app(RelatedMemoryService::class),
        );

        $memory->refresh();
        $this->assertSame('ready', $memory->status);
        $this->assertSame('error_log', $memory->memory_type);
        $this->assertSame('manifest欠落', $memory->summary);
        $this->assertSame(2, AiUsageLog::query()->withoutUserScope()->where('user_id', $user->id)->count());
    }
}
