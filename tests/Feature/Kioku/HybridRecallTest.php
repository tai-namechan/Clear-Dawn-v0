<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Embedding\FakeEmbeddingGateway;
use App\Domain\Kioku\Embedding\SearchDocumentBuilder;
use App\Domain\Kioku\Embedding\VectorStore;
use App\Domain\Kioku\Jobs\GenerateMemoryEmbeddingJob;
use App\Domain\Kioku\Models\KiokuRecallFeedback;
use App\Domain\Kioku\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class HybridRecallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        config([
            'kioku.embedding.enabled' => true,
            'kioku.embedding.provider' => 'fake',
            'kioku.embedding.dimensions' => 8,
            'kioku.semantic_search.enabled' => true,
            'kioku.recall_feedback.enabled' => true,
        ]);
    }

    public function test_recall_returns_top_results_with_reasons_and_falls_back(): void
    {
        $user = User::factory()->create();
        Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'sensitive' => false,
            'title' => '共通パイプライン設計',
            'summary' => '入力元を増やしても後段を共通にする',
            'tags' => ['システム設計'],
            'raw_content' => '入力元と共通処理',
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'sensitive' => true,
            'title' => '秘密の記憶',
            'summary' => 'これは出てはいけない',
            'tags' => ['秘密'],
            'raw_content' => 'sensitive leak candidate',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('kioku.recall.search', [
                'q' => '共通パイプライン',
                'semantic' => 1,
            ]))
            ->assertOk()
            ->assertJsonStructure(['mode', 'session_id', 'results']);

        $ids = collect($response->json('results'))->pluck('memory.id');
        $this->assertNotEmpty($ids);
        $titles = collect($response->json('results'))->pluck('memory.title');
        $this->assertFalse($titles->contains('秘密の記憶'));
        $this->assertNotEmpty($response->json('results.0.reason'));
    }

    public function test_feedback_rejects_other_users_memory(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $memory = Memory::factory()->create(['user_id' => $owner->id, 'status' => 'ready']);

        $this->actingAs($other)
            ->postJson(route('kioku.recall.feedback'), [
                'search_session_id' => (string) Str::uuid(),
                'query_hash' => str_repeat('a', 64),
                'memory_id' => $memory->id,
                'shown_rank' => 1,
                'verdict' => 'hit',
            ])
            ->assertNotFound();

        $this->assertSame(0, KiokuRecallFeedback::query()->withoutUserScope()->count());
    }

    public function test_feedback_stores_hit_for_owner(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create(['user_id' => $user->id, 'status' => 'ready']);
        $sessionId = (string) Str::uuid();

        $this->actingAs($user)
            ->postJson(route('kioku.recall.feedback'), [
                'search_session_id' => $sessionId,
                'query_hash' => str_repeat('b', 64),
                'memory_id' => $memory->id,
                'shown_rank' => 1,
                'verdict' => 'hit',
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->postJson(route('kioku.recall.feedback'), [
                'search_session_id' => $sessionId,
                'query_hash' => str_repeat('b', 64),
                'memory_id' => $memory->id,
                'shown_rank' => 1,
                'verdict' => 'miss',
            ])
            ->assertOk();

        $this->assertSame(1, KiokuRecallFeedback::query()->withoutUserScope()->where('user_id', $user->id)->count());
        $this->assertSame('miss', KiokuRecallFeedback::query()->withoutUserScope()->where('user_id', $user->id)->value('verdict'));
    }

    public function test_legacy_library_search_still_works_when_semantic_off(): void
    {
        config(['kioku.semantic_search.enabled' => false]);
        $user = User::factory()->create();
        Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'title' => 'タグ検索',
            'tags' => ['設計'],
            'raw_content' => 'legacy path',
        ]);

        $this->actingAs($user)
            ->get(route('kioku.memories.index', ['q' => 'legacy']))
            ->assertOk();
    }

    public function test_recall_with_tag_filter_excludes_high_scoring_out_of_scope_vector(): void
    {
        $user = User::factory()->create();

        $inScope = Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'sensitive' => false,
            'memory_type' => 'idea',
            'title' => '共通パイプライン',
            'summary' => 'タグ付きの記憶',
            'tags' => ['設計'],
            'raw_content' => '共通パイプライン 設計',
        ]);
        $outOfScope = Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'sensitive' => false,
            'memory_type' => 'idea',
            'title' => '共通パイプライン設計メモ',
            'summary' => 'クエリとほぼ同じ文言でベクトル順位を高くする',
            'tags' => ['別タグ'],
            'raw_content' => '共通パイプライン設計',
        ]);

        /** @var FakeEmbeddingGateway $fake */
        $fake = app(FakeEmbeddingGateway::class);
        foreach ([$inScope, $outOfScope] as $memory) {
            (new GenerateMemoryEmbeddingJob($memory->id))->handle(
                $fake,
                app(SearchDocumentBuilder::class),
                app(VectorStore::class),
            );
        }

        $response = $this->actingAs($user)
            ->getJson(route('kioku.recall.search', [
                'q' => '共通パイプライン設計',
                'semantic' => 1,
                'tags' => ['設計'],
            ]))
            ->assertOk();

        $ids = collect($response->json('results'))->pluck('memory.id');
        $this->assertTrue($ids->contains($inScope->id));
        $this->assertFalse($ids->contains($outOfScope->id));
    }

    public function test_recall_with_type_filter_keeps_matching_vector_hits(): void
    {
        $user = User::factory()->create();

        $idea = Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'sensitive' => false,
            'memory_type' => 'idea',
            'title' => 'アイデアメモ',
            'summary' => '種類フィルタ対象',
            'tags' => ['x'],
            'raw_content' => 'アイデア',
        ]);
        $log = Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'sensitive' => false,
            'memory_type' => 'decision',
            'title' => 'アイデアの決定ログ',
            'summary' => '種類が違う',
            'tags' => ['x'],
            'raw_content' => 'アイデア',
        ]);

        /** @var FakeEmbeddingGateway $fake */
        $fake = app(FakeEmbeddingGateway::class);
        foreach ([$idea, $log] as $memory) {
            (new GenerateMemoryEmbeddingJob($memory->id))->handle(
                $fake,
                app(SearchDocumentBuilder::class),
                app(VectorStore::class),
            );
        }

        $response = $this->actingAs($user)
            ->getJson(route('kioku.recall.search', [
                'q' => 'アイデア',
                'semantic' => 1,
                'types' => ['idea'],
            ]))
            ->assertOk();

        $ids = collect($response->json('results'))->pluck('memory.id');
        $this->assertTrue($ids->contains($idea->id));
        $this->assertFalse($ids->contains($log->id));
    }
}
