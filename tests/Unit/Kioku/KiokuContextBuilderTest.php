<?php

namespace Tests\Unit\Kioku;

use App\Domain\Kioku\KiokuContextItem;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryLink;
use App\Domain\Kioku\Services\KiokuContextBuilder;
use App\Domain\Kioku\Services\RecallService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class KiokuContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    private KiokuContextBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = app(KiokuContextBuilder::class);
    }

    public function test_excludes_other_users_non_ready_sensitive_and_kioku_letters(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $kept = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'ヨガ欠席',
            'summary' => '仕事でヨガを欠席',
            'raw_content' => 'ヨガを休んだ',
            'tags' => ['ヨガ'],
            'status' => 'ready',
            'sensitive' => false,
            'source_type' => 'manual',
        ]);
        Memory::factory()->create([
            'user_id' => $other->id,
            'title' => 'ヨガ',
            'summary' => 'ヨガ',
            'raw_content' => 'ヨガ',
            'tags' => ['ヨガ'],
            'status' => 'ready',
            'sensitive' => false,
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'ヨガ下書き',
            'summary' => 'ヨガ',
            'raw_content' => 'ヨガ',
            'tags' => ['ヨガ'],
            'status' => 'captured',
            'sensitive' => false,
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'ヨガ機微',
            'summary' => 'ヨガ',
            'raw_content' => 'ヨガ',
            'tags' => ['ヨガ'],
            'status' => 'ready',
            'sensitive' => true,
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'ヨガ手紙',
            'summary' => 'ヨガ',
            'raw_content' => 'ヨガ',
            'tags' => ['ヨガ'],
            'status' => 'ready',
            'sensitive' => false,
            'source_type' => 'kioku_letter',
        ]);

        $items = $this->builder->retrieve(
            userId: (int) $user->id,
            query: 'ヨガ',
            tags: ['ヨガ'],
        );

        $this->assertCount(1, $items);
        $this->assertSame($kept->id, $items->first()->memory->id);
    }

    public function test_scores_title_summary_body_tag_and_seed_link(): void
    {
        $user = User::factory()->create();
        $seed = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'seed',
            'status' => 'ready',
            'sensitive' => false,
        ]);
        $linked = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '無関係タイトル',
            'summary' => '無関係要約',
            'raw_content' => '無関係本文',
            'tags' => [],
            'status' => 'ready',
            'sensitive' => false,
            'importance' => 1,
        ]);
        MemoryLink::query()->create([
            'from_memory_id' => $seed->id,
            'to_memory_id' => $linked->id,
            'kind' => 'related',
            'score' => 1,
            'created_by' => 'system',
        ]);

        $titleHit = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'Vite 調査',
            'summary' => '別件',
            'raw_content' => '別件',
            'tags' => [],
            'status' => 'ready',
            'sensitive' => false,
        ]);
        $summaryHit = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '別タイトル',
            'summary' => 'Vite の学び',
            'raw_content' => '別件',
            'tags' => [],
            'status' => 'ready',
            'sensitive' => false,
        ]);
        $bodyHit = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '別タイトル2',
            'summary' => '別要約',
            'raw_content' => '本文に Vite がある',
            'tags' => [],
            'status' => 'ready',
            'sensitive' => false,
        ]);
        $tagHit = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'タグのみ',
            'summary' => '別要約',
            'raw_content' => '別本文',
            'tags' => ['Vite'],
            'status' => 'ready',
            'sensitive' => false,
        ]);

        $items = $this->builder->retrieve(
            userId: (int) $user->id,
            query: 'Vite',
            tags: ['Vite'],
            seedMemoryIds: [$seed->id],
            topK: 10,
        )->keyBy(fn (KiokuContextItem $item) => $item->memory->id);

        $this->assertSame(4, $items[$titleHit->id]->score);
        $this->assertContains('title:Vite', $items[$titleHit->id]->reasons);
        $this->assertSame(2, $items[$summaryHit->id]->score);
        $this->assertContains('summary:Vite', $items[$summaryHit->id]->reasons);
        $this->assertSame(1, $items[$bodyHit->id]->score);
        $this->assertContains('body:Vite', $items[$bodyHit->id]->reasons);
        $this->assertSame(8, $items[$tagHit->id]->score);
        $this->assertContains('tag:Vite', $items[$tagHit->id]->reasons);
        $this->assertSame(3, $items[$linked->id]->score);
        $this->assertContains('link:seed', $items[$linked->id]->reasons);
    }

    public function test_ignores_foreign_seed_excludes_seed_itself_and_dedupes_bidirectional_links(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $foreignSeed = Memory::factory()->create([
            'user_id' => $other->id,
            'title' => '他人のseed',
            'status' => 'ready',
            'sensitive' => false,
        ]);
        $ownedSeed = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '自分のseed',
            'status' => 'ready',
            'sensitive' => false,
            'tags' => ['seed'],
        ]);
        $neighbor = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '関連',
            'summary' => '関連',
            'raw_content' => '関連',
            'tags' => [],
            'status' => 'ready',
            'sensitive' => false,
        ]);

        MemoryLink::query()->create([
            'from_memory_id' => $neighbor->id,
            'to_memory_id' => $ownedSeed->id,
            'kind' => 'related',
            'score' => 1,
            'created_by' => 'system',
        ]);
        MemoryLink::query()->create([
            'from_memory_id' => $foreignSeed->id,
            'to_memory_id' => $neighbor->id,
            'kind' => 'related',
            'score' => 1,
            'created_by' => 'system',
        ]);

        $items = $this->builder->retrieve(
            userId: (int) $user->id,
            query: '',
            seedMemoryIds: [$ownedSeed->id, $foreignSeed->id],
        );

        $this->assertCount(1, $items);
        $this->assertSame($neighbor->id, $items->first()->memory->id);
        $this->assertFalse($items->contains(fn (KiokuContextItem $item) => $item->memory->id === $ownedSeed->id));
    }

    public function test_respects_top_k_max_chars_and_skips_oversized_to_keep_later_fit(): void
    {
        $user = User::factory()->create();

        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'A',
            'summary' => str_repeat('あ', 50),
            'raw_content' => 'taghit',
            'tags' => ['共通'],
            'status' => 'ready',
            'sensitive' => false,
            'importance' => 5,
            'captured_at' => now()->subMinutes(1),
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'B-long',
            'summary' => str_repeat('い', 200),
            'raw_content' => 'taghit',
            'tags' => ['共通'],
            'status' => 'ready',
            'sensitive' => false,
            'importance' => 4,
            'captured_at' => now()->subMinutes(2),
        ]);
        $small = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'C',
            'summary' => '短い',
            'raw_content' => 'taghit',
            'tags' => ['共通'],
            'status' => 'ready',
            'sensitive' => false,
            'importance' => 3,
            'captured_at' => now()->subMinutes(3),
        ]);

        // Budget fits A (~51) + C (~3) but not B (~206). Skipping B must keep C.
        $items = $this->builder->retrieve(
            userId: (int) $user->id,
            query: '',
            tags: ['共通'],
            topK: 5,
            maxChars: 80,
        );

        $this->assertSame(['A', 'C'], $items->map(fn (KiokuContextItem $item) => $item->memory->title)->all());
        $this->assertTrue($items->contains(fn (KiokuContextItem $item) => $item->memory->id === $small->id));
        $this->assertLessThanOrEqual(80, $items->sum(fn (KiokuContextItem $item) => $item->chars()));
    }

    public function test_zero_results_is_normal_and_payload_omits_full_bodies(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '短い題',
            'summary' => '要約だけ',
            'raw_content' => str_repeat('RAW全文はペイロードに出さない', 20),
            'transcript_text' => str_repeat('TRANSCRIPT全文は出さない', 20),
            'tags' => ['秘密ではない'],
            'status' => 'ready',
            'sensitive' => false,
        ]);

        $empty = $this->builder->retrieve((int) $user->id, '存在しない語彙XYZ');
        $this->assertCount(0, $empty);

        $item = $this->builder->retrieve((int) $user->id, '短い題')->first();
        $this->assertNotNull($item);
        $payload = $item->payload();

        $this->assertSame($memory->id, $payload['memory_id']);
        $this->assertSame('要約だけ', $payload['excerpt']);
        $this->assertArrayNotHasKey('raw_content', $payload);
        $this->assertArrayNotHasKey('transcript_text', $payload);
        $this->assertStringNotContainsString('RAW全文', json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('TRANSCRIPT全文', json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public function test_excerpt_falls_back_to_two_hundred_chars_without_summary(): void
    {
        $user = User::factory()->create();
        $body = str_repeat('本文', 200);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '要約なし',
            'summary' => null,
            'raw_content' => $body,
            'tags' => ['抜粋'],
            'status' => 'ready',
            'sensitive' => false,
        ]);

        $item = $this->builder->retrieve((int) $user->id, '', ['抜粋'])->first();
        $this->assertNotNull($item);
        $this->assertSame(mb_substr($body, 0, 200), $item->excerpt());
    }

    public function test_tie_break_uses_importance_then_captured_at_then_id(): void
    {
        $user = User::factory()->create();
        $olderHigh = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '同点A',
            'summary' => '同点',
            'raw_content' => '同点',
            'tags' => ['同点'],
            'status' => 'ready',
            'sensitive' => false,
            'importance' => 5,
            'captured_at' => now()->subDay(),
        ]);
        $newerLow = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '同点B',
            'summary' => '同点',
            'raw_content' => '同点',
            'tags' => ['同点'],
            'status' => 'ready',
            'sensitive' => false,
            'importance' => 2,
            'captured_at' => now(),
        ]);
        $newerHigh = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '同点C',
            'summary' => '同点',
            'raw_content' => '同点',
            'tags' => ['同点'],
            'status' => 'ready',
            'sensitive' => false,
            'importance' => 5,
            'captured_at' => now()->subHour(),
        ]);

        $ids = $this->builder->retrieve((int) $user->id, '', ['同点'], topK: 3)
            ->map(fn (KiokuContextItem $item) => $item->memory->id)
            ->all();

        $this->assertSame([$newerHigh->id, $olderHigh->id, $newerLow->id], $ids);
    }

    public function test_candidate_pool_is_capped_at_fifty(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 60; $i++) {
            Memory::factory()->create([
                'user_id' => $user->id,
                'title' => "候補 {$i}",
                'summary' => "候補 {$i}",
                'raw_content' => "候補本文 {$i}",
                'tags' => ['候補'],
                'status' => 'ready',
                'sensitive' => false,
                'importance' => 1,
                'captured_at' => now()->subMinutes($i),
            ]);
        }

        $items = $this->builder->retrieve(
            userId: (int) $user->id,
            query: '',
            tags: ['候補'],
            topK: 50,
            maxChars: 1_000_000,
        );

        $this->assertLessThanOrEqual(KiokuContextBuilder::CANDIDATE_LIMIT, $items->count());
        $this->assertCount(KiokuContextBuilder::CANDIDATE_LIMIT, $items);
    }

    public function test_like_metacharacters_in_query_do_not_act_as_wildcards(): void
    {
        $user = User::factory()->create();
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '100%完走',
            'summary' => '達成',
            'raw_content' => '達成',
            'tags' => [],
            'status' => 'ready',
            'sensitive' => false,
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '100完走',
            'summary' => '別',
            'raw_content' => '別',
            'tags' => [],
            'status' => 'ready',
            'sensitive' => false,
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'under_score',
            'summary' => '別',
            'raw_content' => '別',
            'tags' => [],
            'status' => 'ready',
            'sensitive' => false,
        ]);

        $percent = $this->builder->retrieve((int) $user->id, '100%');
        $this->assertCount(1, $percent);
        $this->assertSame('100%完走', $percent->first()->memory->title);

        $underscore = $this->builder->retrieve((int) $user->id, 'under_score');
        $this->assertCount(1, $underscore);
        $this->assertSame('under_score', $underscore->first()->memory->title);
    }

    public function test_does_not_log_memory_bodies(): void
    {
        Log::spy();

        $user = User::factory()->create();
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'ログ対象',
            'summary' => '要約',
            'raw_content' => '本文をログに出してはいけない',
            'tags' => ['ログ'],
            'status' => 'ready',
            'sensitive' => false,
        ]);

        $this->builder->retrieve((int) $user->id, 'ログ対象');

        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('debug');
        Log::shouldNotHaveReceived('warning');
    }

    public function test_cache_hit_returns_same_results_without_db_query(): void
    {
        $user = User::factory()->create();
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'キャッシュ対象',
            'summary' => 'キャッシュ確認',
            'raw_content' => 'キャッシュ確認',
            'tags' => ['キャッシュ'],
            'status' => 'ready',
            'sensitive' => false,
        ]);

        $first = $this->builder->retrieve((int) $user->id, 'キャッシュ', ['キャッシュ']);
        $this->assertCount(1, $first);

        $second = $this->builder->retrieve((int) $user->id, 'キャッシュ', ['キャッシュ']);
        $this->assertCount(1, $second);
        $this->assertSame($first->first()->memory->id, $second->first()->memory->id);
    }

    public function test_memory_change_invalidates_cache_via_version_bump(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '版管理対象',
            'summary' => '版管理確認',
            'raw_content' => '版管理確認',
            'tags' => ['版管理'],
            'status' => 'ready',
            'sensitive' => false,
        ]);

        $this->builder->retrieve((int) $user->id, '版管理', ['版管理']);
        $versionBefore = $user->fresh()->memory_version;

        $memory->update(['status' => 'archived']);
        $versionAfter = $user->fresh()->memory_version;

        $this->assertGreaterThan($versionBefore, $versionAfter);
    }

    public function test_memory_creation_bumps_version(): void
    {
        $user = User::factory()->create();
        $versionBefore = $user->fresh()->memory_version;

        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '新規',
            'status' => 'captured',
            'sensitive' => false,
        ]);

        $this->assertGreaterThan($versionBefore, $user->fresh()->memory_version);
    }

    public function test_memory_deletion_bumps_version(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '削除対象',
            'status' => 'ready',
            'sensitive' => false,
        ]);

        $versionBefore = $user->fresh()->memory_version;
        $memory->delete();
        $this->assertGreaterThan($versionBefore, $user->fresh()->memory_version);
    }

    public function test_query_terms_are_capped_at_max(): void
    {
        $user = User::factory()->create();
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'term1',
            'summary' => 'term1',
            'raw_content' => 'term1',
            'status' => 'ready',
            'sensitive' => false,
        ]);

        $longQuery = implode(' ', array_map(fn (int $i) => "term{$i}", range(1, 20)));
        $items = $this->builder->retrieve((int) $user->id, $longQuery);

        $this->assertLessThanOrEqual(KiokuContextBuilder::MAX_TERMS, 8);
        $this->assertNotEmpty($items);
    }

    public function test_seed_memory_ids_bypass_cache(): void
    {
        $user = User::factory()->create();
        $seed = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => 'seed',
            'status' => 'ready',
            'sensitive' => false,
        ]);
        $neighbor = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '隣接',
            'summary' => '隣接',
            'raw_content' => '隣接',
            'tags' => [],
            'status' => 'ready',
            'sensitive' => false,
        ]);
        MemoryLink::query()->create([
            'from_memory_id' => $seed->id,
            'to_memory_id' => $neighbor->id,
            'kind' => 'related',
            'score' => 1,
            'created_by' => 'system',
        ]);

        Cache::flush();

        $items = $this->builder->retrieve(
            (int) $user->id,
            '',
            seedMemoryIds: [$seed->id],
        );

        $this->assertNotEmpty($items);
    }

    public function test_recall_increments_referenced_count_once_and_respects_count_reference_false(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '参照カウント',
            'summary' => '参照カウント確認',
            'raw_content' => '参照カウント確認',
            'tags' => ['参照'],
            'status' => 'ready',
            'sensitive' => false,
            'referenced_count' => 0,
        ]);

        $recall = app(RecallService::class);
        $recall->memories((int) $user->id, '参照カウント', 5, countReference: true);
        $this->assertSame(1, $memory->fresh()->referenced_count);

        $recall->memories((int) $user->id, '参照カウント', 5, countReference: false);
        $this->assertSame(1, $memory->fresh()->referenced_count);
    }
}
