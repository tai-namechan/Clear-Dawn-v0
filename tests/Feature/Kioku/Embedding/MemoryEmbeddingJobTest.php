<?php

namespace Tests\Feature\Kioku\Embedding;

use App\Domain\Kioku\Embedding\FakeEmbeddingGateway;
use App\Domain\Kioku\Embedding\SearchDocumentBuilder;
use App\Domain\Kioku\Embedding\VectorStore;
use App\Domain\Kioku\Jobs\GenerateMemoryEmbeddingJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryEmbedding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MemoryEmbeddingJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'kioku.embedding.enabled' => true,
            'kioku.embedding.provider' => 'fake',
            'kioku.embedding.model' => 'text-embedding-3-small',
            'kioku.embedding.schema_version' => 'v1',
            'kioku.embedding.dimensions' => 8,
            'kioku.embedding.max_memories_per_user' => 1000,
        ]);
    }

    public function test_search_document_order_and_hash_stability(): void
    {
        $memory = Memory::factory()->make([
            'title' => '設計メモ',
            'summary' => '共通パイプライン',
            'transcript_text' => null,
            'tags' => ['B', 'A'],
            'memory_type' => 'idea',
            'structured_data' => ['insight' => '入口を分ける', 'next_action' => '実装する'],
            'sensitive' => false,
            'status' => 'ready',
            'source_type' => 'manual',
        ]);

        $builder = new SearchDocumentBuilder;
        $doc = $builder->normalizedDocument($memory);
        $this->assertStringStartsWith("title: 設計メモ\nsummary: 共通パイプライン\ntranscript: \ntags: A, B\n", $doc);

        $built = $builder->build($memory);
        $this->assertNotNull($built);
        $again = $builder->build($memory);
        $this->assertSame($built['content_hash'], $again['content_hash']);
    }

    public function test_same_hash_makes_zero_api_calls_on_second_run(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'sensitive' => false,
            'title' => '同じ内容',
            'summary' => '再実行しても課金しない',
            'tags' => ['x'],
        ]);

        /** @var FakeEmbeddingGateway $fake */
        $fake = app(FakeEmbeddingGateway::class);

        (new GenerateMemoryEmbeddingJob($memory->id))->handle(
            $fake,
            app(SearchDocumentBuilder::class),
            app(VectorStore::class),
        );
        $this->assertSame(1, $fake->calls);

        (new GenerateMemoryEmbeddingJob($memory->id))->handle(
            $fake,
            app(SearchDocumentBuilder::class),
            app(VectorStore::class),
        );
        $this->assertSame(1, $fake->calls);
        $this->assertSame(1, MemoryEmbedding::query()->withoutUserScope()->where('memory_id', $memory->id)->count());
    }

    public function test_field_change_triggers_one_more_api_call(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'sensitive' => false,
            'title' => '変更前',
            'summary' => '要約',
            'tags' => ['a'],
        ]);

        /** @var FakeEmbeddingGateway $fake */
        $fake = app(FakeEmbeddingGateway::class);

        (new GenerateMemoryEmbeddingJob($memory->id))->handle(
            $fake,
            app(SearchDocumentBuilder::class),
            app(VectorStore::class),
        );

        $memory->update(['title' => '変更後']);

        (new GenerateMemoryEmbeddingJob($memory->id))->handle(
            $fake,
            app(SearchDocumentBuilder::class),
            app(VectorStore::class),
        );

        $this->assertSame(2, $fake->calls);
    }

    public function test_sensitive_memory_skips_api_and_deletes_vectors(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'sensitive' => false,
            'title' => '後で秘密',
            'summary' => '要約',
            'tags' => ['s'],
        ]);

        /** @var FakeEmbeddingGateway $fake */
        $fake = app(FakeEmbeddingGateway::class);
        (new GenerateMemoryEmbeddingJob($memory->id))->handle(
            $fake,
            app(SearchDocumentBuilder::class),
            app(VectorStore::class),
        );
        $this->assertSame(1, MemoryEmbedding::query()->withoutUserScope()->where('memory_id', $memory->id)->count());

        $memory->update(['sensitive' => true]);
        $this->assertSame(0, MemoryEmbedding::query()->withoutUserScope()->where('memory_id', $memory->id)->count());

        $before = $fake->calls;
        (new GenerateMemoryEmbeddingJob($memory->id))->handle(
            $fake,
            app(SearchDocumentBuilder::class),
            app(VectorStore::class),
        );
        $this->assertSame($before, $fake->calls);
    }

    public function test_vector_nearest_respects_owner_boundary(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $ownerMemory = Memory::factory()->create([
            'user_id' => $owner->id,
            'status' => 'ready',
            'title' => 'owner only',
            'summary' => 'mine',
            'tags' => ['o'],
        ]);
        $otherMemory = Memory::factory()->create([
            'user_id' => $other->id,
            'status' => 'ready',
            'title' => 'other',
            'summary' => 'theirs',
            'tags' => ['x'],
        ]);

        /** @var FakeEmbeddingGateway $fake */
        $fake = app(FakeEmbeddingGateway::class);
        foreach ([$ownerMemory, $otherMemory] as $memory) {
            (new GenerateMemoryEmbeddingJob($memory->id))->handle(
                $fake,
                app(SearchDocumentBuilder::class),
                app(VectorStore::class),
            );
        }

        $ownerEmbedding = MemoryEmbedding::query()->withoutUserScope()->where('memory_id', $ownerMemory->id)->sole();
        $hits = app(VectorStore::class)->nearest((int) $owner->id, $ownerEmbedding->vectorArray(), 10);
        $ids = array_column($hits, 'memory_id');
        $this->assertContains($ownerMemory->id, $ids);
        $this->assertNotContains($otherMemory->id, $ids);
    }

    public function test_backfill_requires_user_option(): void
    {
        $this->artisan('kioku:embeddings:backfill')
            ->assertFailed();
    }

    public function test_backfill_dry_run_does_not_dispatch(): void
    {
        $user = User::factory()->create();
        Memory::factory()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'sensitive' => false,
        ]);

        $this->artisan('kioku:embeddings:backfill', [
            '--user' => $user->id,
            '--dry-run' => true,
        ])->assertSuccessful();
    }
}
