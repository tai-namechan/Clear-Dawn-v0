<?php

namespace Tests\Feature\Kioku;

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

        $this->actingAs($user)
            ->postJson(route('kioku.recall.feedback'), [
                'search_session_id' => (string) Str::uuid(),
                'query_hash' => str_repeat('b', 64),
                'memory_id' => $memory->id,
                'shown_rank' => 1,
                'verdict' => 'hit',
            ])
            ->assertCreated();

        $this->assertSame(1, KiokuRecallFeedback::query()->withoutUserScope()->where('user_id', $user->id)->count());
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
}
