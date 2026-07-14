<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\KiokuLetterCandidateService;
use App\Domain\Kioku\Services\KiokuLetterGenerator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KiokuLetterGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.anthropic.api_key' => 'test-key',
            'kioku.concierge.enabled' => true,
        ]);
    }

    /**
     * Candidate-eligible ready memory: enriched summary and captured well
     * before the 14-day cooldown window.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function readyMemory(User $user, array $attributes = []): Memory
    {
        return Memory::factory()->create(array_merge([
            'user_id' => $user->id,
            'captured_at' => now()->subDays(30),
        ], $attributes));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function fakeLetterResponse(array $items, string $intro = '今週のまとめです。'): void
    {
        Http::fake([
            $this->anthropicFakePattern() => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'schema_version' => 1,
                        'intro' => $intro,
                        'items' => $items,
                    ], JSON_UNESCAPED_UNICODE),
                ]],
                'usage' => ['input_tokens' => 100, 'output_tokens' => 200],
            ]),
        ]);
    }

    private function generate(User $user, string $character = 'shiori', ?string $context = null): KiokuLetter
    {
        return app(KiokuLetterGenerator::class)->generate(
            $user,
            CarbonImmutable::now(),
            $character,
            $context,
        );
    }

    /**
     * The prompt as the model would see it (system + message contents),
     * decoded from the JSON body so unicode escaping cannot mask matches.
     */
    private function sentPromptText(Request $request): string
    {
        /** @var array{system?: string, messages?: list<array{content?: string}>} $payload */
        $payload = json_decode((string) $request->body(), true) ?: [];

        return ($payload['system'] ?? '').' '
            .implode(' ', array_column($payload['messages'] ?? [], 'content'));
    }

    public function test_candidates_exclude_sensitive_non_ready_letter_logs_and_missing_summary(): void
    {
        $user = User::factory()->create();
        $eligible = $this->readyMemory($user, ['title' => '候補になる記憶']);
        $this->readyMemory($user, ['sensitive' => true, 'title' => '出してはいけない記憶']);
        $this->readyMemory($user, ['status' => 'captured']);
        $this->readyMemory($user, ['source_type' => 'kioku_letter', 'title' => '先週の評価ログ']);
        $this->readyMemory($user, ['summary' => null]);
        $this->readyMemory($user, ['summary' => '']);
        $this->readyMemory(User::factory()->create(), ['title' => '他人の記憶']);

        $candidates = app(KiokuLetterCandidateService::class)->candidatesFor((int) $user->id);

        $this->assertSame([$eligible->id], $candidates->pluck('id')->all());
    }

    public function test_candidates_respect_fourteen_day_cooldown(): void
    {
        $user = User::factory()->create();
        $cooled = $this->readyMemory($user, ['captured_at' => now()->subDays(20)]);
        // Captured long ago but surfaced by a letter 3 days ago: cooling down.
        $this->readyMemory($user, [
            'captured_at' => now()->subDays(40),
            'last_referenced_at' => now()->subDays(3),
        ]);
        // Captured this week: too fresh to be "forgotten".
        $this->readyMemory($user, ['captured_at' => now()->subDays(3)]);

        $candidates = app(KiokuLetterCandidateService::class)->candidatesFor((int) $user->id);

        $this->assertSame([$cooled->id], $candidates->pluck('id')->all());
    }

    public function test_candidates_are_capped_at_eighty_with_fixed_order(): void
    {
        $user = User::factory()->create();
        Memory::factory()->count(82)->create([
            'user_id' => $user->id,
            'captured_at' => now()->subDays(30),
            'importance' => 3,
        ]);
        $mostImportant = $this->readyMemory($user, ['importance' => 5]);
        $oldest = $this->readyMemory($user, [
            'importance' => 5,
            'captured_at' => now()->subDays(300),
        ]);

        $candidates = app(KiokuLetterCandidateService::class)->candidatesFor((int) $user->id);

        $this->assertCount(KiokuLetterCandidateService::MAX_CANDIDATES, $candidates);
        // importance DESC first, then least-recently-referenced first.
        $this->assertSame($oldest->id, $candidates[0]->id);
        $this->assertSame($mostImportant->id, $candidates[1]->id);
    }

    public function test_generation_with_no_candidates_is_empty_without_ai_call(): void
    {
        Http::fake();
        $user = User::factory()->create();

        $letter = $this->generate($user);

        Http::assertNothingSent();
        $this->assertSame(KiokuLetter::STATUS_EMPTY, $letter->status);
        $this->assertSame(0, $letter->item_count);
        $this->assertSame(0, $letter->candidate_count);
        $this->assertNotNull($letter->published_at);
    }

    public function test_ai_failure_is_failed_and_distinct_from_empty(): void
    {
        Http::fake([
            $this->anthropicFakePattern() => Http::response(['error' => 'down'], 500),
        ]);
        $user = User::factory()->create();
        $this->readyMemory($user);

        try {
            $this->generate($user);
            $this->fail('AI failure should raise a KiokuLetterException.');
        } catch (KiokuLetterException) {
            // expected
        }

        $letter = KiokuLetter::query()->withoutUserScope()->sole();
        $this->assertSame(KiokuLetter::STATUS_FAILED, $letter->status);
        $this->assertNull($letter->published_at);
        $this->assertDatabaseCount('kioku_letter_items', 0);
    }

    public function test_generation_persists_valid_items_and_never_sends_sensitive_or_raw(): void
    {
        $user = User::factory()->create();
        $memory = $this->readyMemory($user, [
            'title' => 'Viteの設定でハマった件',
            'summary' => 'Viteのbuildでmanifestが壊れた原因と対処。',
            'raw_content' => 'この生原文はAIに渡してはいけない秘密のテキスト',
        ]);
        $related = $this->readyMemory($user, ['title' => '関連する学び']);
        $this->readyMemory($user, [
            'sensitive' => true,
            'title' => '誰にも出したくない記憶タイトル',
        ]);

        $this->fakeLetterResponse([[
            'memory_id' => $memory->id,
            'headline' => '30秒保存の次に見るべきもの',
            'why_now' => '実機確認が終わり、検証を再開する時期だからです。',
            'related_memory_ids' => [$related->id],
        ]]);

        $letter = $this->generate($user, 'shiori', '今週は文字起こしを接続した');

        $this->assertSame(KiokuLetter::STATUS_PUBLISHED, $letter->status);
        $this->assertSame(1, $letter->item_count);
        $this->assertSame('shiori', $letter->character_variant);
        $this->assertSame('kioku.concierge.letter.v1', $letter->prompt_key);
        $this->assertNotNull($letter->generation_meta['usage_request_id'] ?? null);

        $item = $letter->items()->sole();
        $this->assertSame($memory->id, $item->memory_id);
        $this->assertSame(1, $item->position);
        $this->assertSame('Viteの設定でハマった件', $item->title_snapshot);
        $this->assertSame([$related->id], $item->related_memory_ids);

        Http::assertSent(function (Request $request): bool {
            $prompt = $this->sentPromptText($request);

            return str_contains($prompt, 'Viteの設定でハマった件')
                && str_contains($prompt, '今週は文字起こしを接続した')
                && ! str_contains($prompt, '誰にも出したくない記憶タイトル')
                && ! str_contains($prompt, '秘密のテキスト');
        });
    }

    public function test_prompt_never_mentions_the_characters(): void
    {
        $user = User::factory()->create();
        $this->readyMemory($user);
        $this->fakeLetterResponse([]);

        $this->generate($user, 'nagi');

        Http::assertSent(function (Request $request): bool {
            $prompt = $this->sentPromptText($request);

            return str_contains($prompt, 'コンシェルジュ手紙')
                && ! str_contains($prompt, 'シオリ')
                && ! str_contains($prompt, 'ナギ')
                && ! str_contains($prompt, 'nagi')
                && ! str_contains($prompt, 'shiori');
        });
    }

    public function test_invalid_items_are_dropped_without_regeneration(): void
    {
        $user = User::factory()->create();
        $valid = $this->readyMemory($user);
        $other = $this->readyMemory($user);

        $this->fakeLetterResponse([
            ['memory_id' => 'not-a-candidate', 'headline' => '候補外', 'why_now' => 'x'],
            ['memory_id' => $valid->id, 'headline' => str_repeat('あ', 61), 'why_now' => '見出しが長すぎる'],
            [
                'memory_id' => $valid->id,
                'headline' => '有効な見出し',
                'why_now' => '今も未解決の問題に直結しているからです。',
                'related_memory_ids' => ['ghost-id', $valid->id, $other->id],
            ],
            ['memory_id' => $valid->id, 'headline' => '重複', 'why_now' => '同じ記憶の二回目'],
            ['memory_id' => $other->id, 'headline' => 'why_nowが長すぎる', 'why_now' => str_repeat('い', 181)],
        ]);

        $letter = $this->generate($user);

        $this->assertSame(KiokuLetter::STATUS_PUBLISHED, $letter->status);
        $this->assertSame(1, $letter->item_count);

        $item = $letter->items()->sole();
        $this->assertSame($valid->id, $item->memory_id);
        $this->assertSame('有効な見出し', $item->headline);
        // ghost id and self-reference are dropped; only real candidates stay.
        $this->assertSame([$other->id], $item->related_memory_ids);
        Http::assertSentCount(1);
    }

    public function test_items_are_capped_at_five(): void
    {
        $user = User::factory()->create();
        $memories = collect(range(1, 7))->map(fn () => $this->readyMemory($user));

        $this->fakeLetterResponse($memories->map(fn (Memory $memory, int $index) => [
            'memory_id' => $memory->id,
            'headline' => "見出し{$index}",
            'why_now' => '今週の文脈に直結しているからです。',
        ])->values()->all());

        $letter = $this->generate($user);

        $this->assertSame(5, $letter->item_count);
        $this->assertSame([1, 2, 3, 4, 5], $letter->items()->pluck('position')->all());
    }

    public function test_all_invalid_items_result_in_empty_not_failed(): void
    {
        $user = User::factory()->create();
        $this->readyMemory($user);

        $this->fakeLetterResponse([
            ['memory_id' => 'ghost-1', 'headline' => 'x', 'why_now' => 'y'],
        ]);

        $letter = $this->generate($user);

        $this->assertSame(KiokuLetter::STATUS_EMPTY, $letter->status);
        $this->assertSame(0, $letter->item_count);
        $this->assertNotNull($letter->published_at);
    }

    public function test_same_user_and_week_is_never_generated_twice(): void
    {
        $user = User::factory()->create();
        $this->readyMemory($user);
        $this->fakeLetterResponse([]);

        $this->generate($user);

        $this->expectException(KiokuLetterException::class);

        try {
            $this->generate($user, 'nagi');
        } finally {
            $this->assertSame(1, KiokuLetter::query()->withoutUserScope()->count());
        }
    }

    public function test_unknown_character_is_rejected_before_any_row_or_ai_call(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->readyMemory($user);

        $this->expectException(KiokuLetterException::class);

        try {
            $this->generate($user, 'mystery');
        } finally {
            Http::assertNothingSent();
            $this->assertDatabaseCount('kioku_letters', 0);
        }
    }

    public function test_command_generates_for_both_characters(): void
    {
        $user = User::factory()->create();
        $this->readyMemory($user);
        $this->fakeLetterResponse([]);

        $this->artisan('kioku:letters:generate', [
            'userId' => $user->id,
            '--character' => 'nagi',
            '--week' => '2026-07-15',
        ])->assertSuccessful();

        $letter = KiokuLetter::query()->withoutUserScope()->sole();
        $this->assertSame('nagi', $letter->character_variant);
        // --week is normalized to that week's Monday.
        $this->assertSame('2026-07-13', $letter->week_start->toDateString());
    }

    public function test_command_dry_run_reports_breakdown_without_ai_or_rows(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $this->readyMemory($user);
        $this->readyMemory($user, ['sensitive' => true]);

        $this->artisan('kioku:letters:generate', [
            'userId' => $user->id,
            '--dry-run' => true,
        ])->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('kioku_letters', 0);
    }

    public function test_command_requires_concierge_enabled_and_valid_input(): void
    {
        config(['kioku.concierge.enabled' => false]);
        $user = User::factory()->create();

        $this->artisan('kioku:letters:generate', ['userId' => $user->id])
            ->assertFailed();

        config(['kioku.concierge.enabled' => true]);

        $this->artisan('kioku:letters:generate', ['userId' => 999999])
            ->assertFailed();

        $this->artisan('kioku:letters:generate', [
            'userId' => $user->id,
            '--character' => 'mystery',
        ])->assertFailed();

        $this->assertDatabaseCount('kioku_letters', 0);
    }
}
