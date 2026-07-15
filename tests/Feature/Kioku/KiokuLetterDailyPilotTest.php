<?php

namespace Tests\Feature\Kioku;

use App\Console\Commands\GenerateKiokuLetterCommand;
use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\Jobs\GenerateDailyKiokuLetterJob;
use App\Domain\Kioku\KiokuConciergeScheduleState;
use App\Domain\Kioku\KiokuLetterCadence;
use App\Domain\Kioku\KiokuLetterMode;
use App\Domain\Kioku\Models\KiokuConciergeSchedule;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Models\KiokuLetterItem;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\KiokuConciergePilotService;
use App\Domain\Kioku\Services\KiokuLetterCandidateService;
use App\Domain\Kioku\Services\KiokuLetterGenerator;
use App\Domain\Kioku\Services\KiokuLetterHaltGuard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class KiokuLetterDailyPilotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.anthropic.api_key' => 'test-key',
            'kioku.concierge.enabled' => true,
            'kioku.concierge.test_enabled' => true,
        ]);

        Http::preventStrayRequests();
    }

    /**
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
    private function fakeLetterResponse(array $items, string $intro = 'まとめです。'): void
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

    public function test_sensitive_leak_quarantines_memory_and_blocks_all_generation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $memory = $this->readyMemory($user);
        // Keep other user's memory non-candidate so their generate needs no AI.
        $otherMemory = $this->readyMemory($other, ['sensitive' => true]);

        $letter = KiokuLetter::factory()->create([
            'user_id' => $user->id,
            'item_count' => 1,
        ]);
        $item = KiokuLetterItem::factory()->create([
            'letter_id' => $letter->id,
            'memory_id' => $memory->id,
            'position' => 1,
        ]);

        $this->actingAs($user)->put(
            route('kioku.letters.items.verdict', [$letter, $item]),
            ['verdict' => KiokuLetterItem::VERDICT_SENSITIVE_LEAK],
        );

        $this->assertTrue($memory->fresh()->sensitive);
        $this->assertTrue($otherMemory->fresh()->sensitive); // unchanged pre-existing sensitive
        $this->assertNotNull($letter->fresh()->halted_at);

        $candidates = app(KiokuLetterCandidateService::class)->candidatesFor((int) $user->id);
        $this->assertFalse($candidates->contains('id', $memory->id));

        Http::fake();
        $this->readyMemory($user, ['title' => '別の候補']);

        $this->assertTrue(
            app(KiokuLetterHaltGuard::class)
                ->hasUnresolvedHalt((int) $user->id),
        );

        try {
            app(KiokuLetterGenerator::class)->generate(
                $user,
                CarbonImmutable::now()->addWeek(),
                'shiori',
                null,
            );
            $this->fail('Unresolved halt must block generation.');
        } catch (KiokuLetterException $e) {
            $this->assertStringContainsString('unresolved sensitive_leak halt', $e->getMessage());
        }

        try {
            app(KiokuLetterGenerator::class)->generateLetter(
                $user,
                'shiori',
                null,
                KiokuLetterMode::Test,
                KiokuLetterCadence::Weekly,
                CarbonImmutable::now(),
            );
            $this->fail('Test generation must also respect unresolved halt.');
        } catch (KiokuLetterException $e) {
            $this->assertStringContainsString('unresolved sensitive_leak halt', $e->getMessage());
        }

        Http::assertNothingSent();
        $this->assertSame(
            1,
            KiokuLetter::query()->withoutUserScope()->where('user_id', $user->id)->count(),
        );

        // Other users are unaffected.
        Http::fake();
        $otherLetter = app(KiokuLetterGenerator::class)->generate(
            $other,
            CarbonImmutable::now(),
            'shiori',
            null,
        );
        $this->assertSame(KiokuLetter::STATUS_EMPTY, $otherLetter->status);
        Http::assertNothingSent();
    }

    public function test_resolve_halt_allows_generation_again_without_clearing_sensitive(): void
    {
        $user = User::factory()->create();
        $memory = $this->readyMemory($user);
        $letter = KiokuLetter::factory()->create([
            'user_id' => $user->id,
            'item_count' => 1,
            'status' => KiokuLetter::STATUS_HALTED,
            'halted_at' => now(),
        ]);
        KiokuLetterItem::factory()->create([
            'letter_id' => $letter->id,
            'memory_id' => $memory->id,
            'position' => 1,
            'verdict' => KiokuLetterItem::VERDICT_SENSITIVE_LEAK,
            'verdict_at' => now(),
        ]);
        $memory->update(['sensitive' => true]);

        KiokuConciergeSchedule::factory()->active()->create([
            'user_id' => $user->id,
            'state' => KiokuConciergeScheduleState::Halted->value,
            'pause_reason' => 'sensitive_leak',
        ]);

        $this->artisan('kioku:letters:resolve-halt', [
            'userId' => $user->id,
            'letterId' => $letter->id,
            '--note' => '確認済み。除外を維持',
        ])->assertSuccessful();

        $letter->refresh();
        $this->assertNotNull($letter->halt_resolved_at);
        $this->assertTrue($memory->fresh()->sensitive);

        $schedule = KiokuConciergeSchedule::query()->withoutUserScope()->where('user_id', $user->id)->sole();
        $this->assertSame(KiokuConciergeScheduleState::Active->value, $schedule->state);

        $this->fakeLetterResponse([]);
        $next = app(KiokuLetterGenerator::class)->generate(
            $user,
            CarbonImmutable::parse('2026-08-03'),
            'shiori',
            null,
        );
        $this->assertNotSame($letter->id, $next->id);
    }

    public function test_no_force_bypass_on_halt_guard(): void
    {
        $user = User::factory()->create();
        KiokuLetter::factory()->create([
            'user_id' => $user->id,
            'status' => KiokuLetter::STATUS_HALTED,
            'halted_at' => now(),
        ]);

        $this->artisan('kioku:letters:generate', [
            'userId' => $user->id,
        ])->assertFailed();

        $definition = (new GenerateKiokuLetterCommand)->getDefinition();
        $this->assertFalse($definition->hasOption('force'));

        $this->assertSame(1, KiokuLetter::query()->withoutUserScope()->where('user_id', $user->id)->count());
    }

    public function test_same_week_can_have_multiple_daily_letters_but_one_per_day(): void
    {
        $user = User::factory()->create();
        $this->readyMemory($user);
        $this->fakeLetterResponse([]);

        $generator = app(KiokuLetterGenerator::class);
        $a = $generator->generateLetter(
            $user,
            'shiori',
            null,
            KiokuLetterMode::Live,
            KiokuLetterCadence::Daily,
            CarbonImmutable::parse('2026-07-15'),
            1,
        );
        $b = $generator->generateLetter(
            $user,
            'shiori',
            null,
            KiokuLetterMode::Live,
            KiokuLetterCadence::Daily,
            CarbonImmutable::parse('2026-07-16'),
            2,
        );

        $this->assertSame('2026-07-13', $a->week_start->toDateString());
        $this->assertSame('2026-07-13', $b->week_start->toDateString());
        $this->assertSame(2, KiokuLetter::query()->withoutUserScope()->where('user_id', $user->id)->count());

        $this->expectException(KiokuLetterException::class);
        $generator->generateLetter(
            $user,
            'shiori',
            null,
            KiokuLetterMode::Live,
            KiokuLetterCadence::Daily,
            CarbonImmutable::parse('2026-07-15'),
            1,
        );
    }

    public function test_test_letters_do_not_consume_live_slots_or_cooldown(): void
    {
        $user = User::factory()->create();
        $memory = $this->readyMemory($user);
        $this->fakeLetterResponse([[
            'memory_id' => $memory->id,
            'headline' => 'テスト見出し',
            'why_now' => 'テスト用の why_now です。',
            'related_memory_ids' => [],
        ]]);

        $this->artisan('kioku:letters:test', [
            'userId' => $user->id,
            '--character' => 'shiori',
        ])->assertSuccessful();

        $test = KiokuLetter::query()->withoutUserScope()->where('mode', 'test')->sole();
        $this->assertSame(1, $test->item_count);

        $memory->refresh();
        $this->assertNull($memory->last_delivered_at);
        $this->assertSame(0, $memory->referenced_count);

        $this->actingAs($user)->post(route('kioku.letters.open', $test));
        $this->assertNull($memory->fresh()->last_referenced_at);
        $this->assertSame(0, $memory->fresh()->referenced_count);

        // Live weekly slot for the same week is still free.
        $this->fakeLetterResponse([]);
        $live = app(KiokuLetterGenerator::class)->generate(
            $user,
            CarbonImmutable::now(),
            'nagi',
            null,
        );
        $this->assertSame(KiokuLetterMode::Live->value, $live->mode);
        $this->assertSame(2, KiokuLetter::query()->withoutUserScope()->where('user_id', $user->id)->count());
    }

    public function test_daily_max_two_weekly_max_five(): void
    {
        $user = User::factory()->create();
        $memories = collect(range(1, 7))->map(fn () => $this->readyMemory($user));
        $payload = $memories->map(fn (Memory $memory, int $i) => [
            'memory_id' => $memory->id,
            'headline' => "見出し{$i}",
            'why_now' => '文脈に直結しているからです。',
            'related_memory_ids' => [],
        ])->values()->all();

        $this->fakeLetterResponse($payload);
        $daily = app(KiokuLetterGenerator::class)->generateLetter(
            $user,
            'shiori',
            null,
            KiokuLetterMode::Live,
            KiokuLetterCadence::Daily,
            CarbonImmutable::parse('2026-07-15'),
            1,
        );
        $this->assertSame(2, $daily->item_count);

        $this->fakeLetterResponse($payload);
        $weekly = app(KiokuLetterGenerator::class)->generateLetter(
            $user,
            'shiori',
            null,
            KiokuLetterMode::Live,
            KiokuLetterCadence::Weekly,
            CarbonImmutable::parse('2026-08-03'),
        );
        $this->assertSame(5, $weekly->item_count);
    }

    public function test_last_delivered_at_blocks_resend_even_when_unread(): void
    {
        $user = User::factory()->create();
        $memory = $this->readyMemory($user);
        $this->fakeLetterResponse([[
            'memory_id' => $memory->id,
            'headline' => '一度届けた記憶',
            'why_now' => '昨日届けたはずです。',
            'related_memory_ids' => [],
        ]]);

        app(KiokuLetterGenerator::class)->generateLetter(
            $user,
            'shiori',
            null,
            KiokuLetterMode::Live,
            KiokuLetterCadence::Daily,
            CarbonImmutable::parse('2026-07-15'),
            1,
        );

        $memory->refresh();
        $this->assertNotNull($memory->last_delivered_at);
        $this->assertNull($memory->last_referenced_at);

        $candidates = app(KiokuLetterCandidateService::class)->candidatesFor((int) $user->id);
        $this->assertFalse($candidates->contains('id', $memory->id));
    }

    public function test_pilot_window_is_fourteen_days_without_backfill(): void
    {
        $user = User::factory()->create();
        $this->readyMemory($user);
        $this->fakeLetterResponse([]);

        $pilot = app(KiokuConciergePilotService::class);
        $pilot->start(
            $user,
            CarbonImmutable::parse('2026-07-15'),
            14,
            '21:00',
            'Asia/Tokyo',
            sendNow: false,
            dryRun: false,
        );

        $schedule = KiokuConciergeSchedule::query()->withoutUserScope()->where('user_id', $user->id)->sole();

        // Day 1
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-15 21:00:00', 'Asia/Tokyo')->utc());
        $day1 = $pilot->deliverForSchedule($schedule);
        $this->assertNotNull($day1);
        $this->assertSame(1, $day1->pilot_day);

        // Miss day 2 entirely — no backfill when jumping to day 3.
        $schedule->refresh();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-17 21:00:00', 'Asia/Tokyo')->utc());
        $schedule->forceFill([
            'next_delivery_at' => CarbonImmutable::parse('2026-07-17 21:00:00', 'Asia/Tokyo')->utc(),
            'state' => KiokuConciergeScheduleState::Active->value,
        ])->save();

        $day3 = $pilot->deliverForSchedule($schedule->fresh());
        $this->assertNotNull($day3);
        $this->assertSame('2026-07-17', $day3->delivery_date->toDateString());
        $this->assertSame(2, KiokuLetter::query()->withoutUserScope()->where('user_id', $user->id)->where('cadence', 'daily')->count());

        // Day 14 then completed; day 15 generates nothing.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-28 21:00:00', 'Asia/Tokyo')->utc());
        $schedule->forceFill([
            'state' => KiokuConciergeScheduleState::Active->value,
            'next_delivery_at' => CarbonImmutable::now('UTC'),
        ])->save();
        $day14 = $pilot->deliverForSchedule($schedule->fresh());
        $this->assertNotNull($day14);
        $this->assertSame(KiokuConciergeScheduleState::Completed->value, $schedule->fresh()->state);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-29 21:00:00', 'Asia/Tokyo')->utc());
        $none = $pilot->deliverForSchedule($schedule->fresh());
        $this->assertNull($none);

        CarbonImmutable::setTestNow();
    }

    public function test_three_consecutive_unread_pauses_before_fourth_ai_call(): void
    {
        $user = User::factory()->create();
        $this->readyMemory($user);

        foreach (['2026-07-15', '2026-07-16', '2026-07-17'] as $i => $date) {
            KiokuLetter::factory()->daily($date, $i + 1)->create([
                'user_id' => $user->id,
                'status' => KiokuLetter::STATUS_PUBLISHED,
                'published_at' => CarbonImmutable::parse($date.' 21:00:00', 'Asia/Tokyo')->utc()->subDay(),
                'opened_at' => null,
            ]);
        }

        $schedule = KiokuConciergeSchedule::factory()->active()->create([
            'user_id' => $user->id,
            'next_delivery_at' => CarbonImmutable::parse('2026-07-18 21:00:00', 'Asia/Tokyo')->utc(),
        ]);

        Http::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-18 21:00:00', 'Asia/Tokyo')->utc());

        $letter = app(KiokuConciergePilotService::class)->deliverForSchedule($schedule);
        $this->assertNull($letter);
        $this->assertSame(KiokuConciergeScheduleState::Paused->value, $schedule->fresh()->state);
        Http::assertNothingSent();
        $this->assertSame(
            3,
            KiokuLetter::query()->withoutUserScope()->where('user_id', $user->id)->where('cadence', 'daily')->count(),
        );

        CarbonImmutable::setTestNow();
    }

    public function test_failed_retry_only_and_empty_rates_are_null(): void
    {
        $user = User::factory()->create();
        $failed = KiokuLetter::factory()->create([
            'user_id' => $user->id,
            'status' => KiokuLetter::STATUS_FAILED,
            'published_at' => null,
            'generation_meta' => ['failures' => [['reason' => 'timeout']]],
        ]);

        $this->readyMemory($user);
        $this->fakeLetterResponse([]);

        $this->artisan('kioku:letters:retry-failed', [
            'userId' => $user->id,
            'letterId' => $failed->id,
        ])->assertSuccessful();

        $failed->refresh();
        $this->assertSame(KiokuLetter::STATUS_EMPTY, $failed->status);
        $this->assertSame(1, $failed->retry_count);
        $this->assertNotEmpty($failed->generation_meta['failures'] ?? []);

        $published = KiokuLetter::factory()->daily('2026-07-20', 6)->create([
            'user_id' => $user->id,
            'status' => KiokuLetter::STATUS_PUBLISHED,
        ]);
        $this->artisan('kioku:letters:retry-failed', [
            'userId' => $user->id,
            'letterId' => $published->id,
        ])->assertFailed();

        $this->actingAs($user)->post(route('kioku.letters.complete', $failed));
        $failed->refresh();
        $evaluation = Memory::query()->withoutUserScope()->findOrFail($failed->evaluation_memory_id);
        $this->assertNull($evaluation->structured_data['hit_rate']);
        $this->assertNull($evaluation->structured_data['useful_rate']);
        $this->assertTrue($evaluation->structured_data['empty']);
    }

    public function test_verdict_before_open_still_records_open_once(): void
    {
        $user = User::factory()->create();
        $memory = $this->readyMemory($user);
        $letter = KiokuLetter::factory()->create([
            'user_id' => $user->id,
            'item_count' => 1,
        ]);
        $item = KiokuLetterItem::factory()->create([
            'letter_id' => $letter->id,
            'memory_id' => $memory->id,
            'position' => 1,
        ]);

        $this->actingAs($user)->put(
            route('kioku.letters.items.verdict', [$letter, $item]),
            ['verdict' => 'hit'],
        );
        $this->assertSame(KiokuLetter::STATUS_EVALUATING, $letter->fresh()->status);
        $this->assertNull($letter->fresh()->opened_at);

        $this->actingAs($user)->post(route('kioku.letters.open', $letter));
        $letter->refresh();
        $this->assertNotNull($letter->opened_at);
        $this->assertSame(KiokuLetter::STATUS_EVALUATING, $letter->status);
        $this->assertSame(1, $memory->fresh()->referenced_count);

        $this->actingAs($user)->post(route('kioku.letters.open', $letter));
        $this->assertSame(1, $memory->fresh()->referenced_count);
    }

    public function test_completed_letter_rejects_late_verdict(): void
    {
        $user = User::factory()->create();
        $memory = $this->readyMemory($user);
        $letter = KiokuLetter::factory()->create([
            'user_id' => $user->id,
            'item_count' => 1,
            'completed_at' => now(),
            'status' => KiokuLetter::STATUS_EVALUATED,
        ]);
        $item = KiokuLetterItem::factory()->create([
            'letter_id' => $letter->id,
            'memory_id' => $memory->id,
            'position' => 1,
            'verdict' => 'hit',
            'verdict_at' => now(),
        ]);

        $this->actingAs($user)->put(
            route('kioku.letters.items.verdict', [$letter, $item]),
            ['verdict' => 'miss'],
        );

        $this->assertSame('hit', $item->fresh()->verdict);
    }

    public function test_preview_is_fixture_only_and_test_flag_gates_command(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('kioku.letters.preview', ['character' => 'shiori', 'case' => 'five']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Kioku/Letter')
                ->where('preview', true)
                ->where('letter.item_count', 5)
                ->where('letter.character_variant', 'shiori'),
            );

        $this->assertDatabaseCount('kioku_letters', 0);

        config(['kioku.concierge.test_enabled' => false]);
        $this->artisan('kioku:letters:test', ['userId' => $user->id])->assertFailed();
    }

    public function test_dispatcher_dispatches_unique_jobs_not_inline_success(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $schedule = KiokuConciergeSchedule::factory()->active()->create([
            'user_id' => $user->id,
            'next_delivery_at' => now()->subMinute(),
        ]);

        $this->artisan('kioku:letters:pilot:dispatch-due')->assertSuccessful();

        Queue::assertPushed(GenerateDailyKiokuLetterJob::class, function (GenerateDailyKiokuLetterJob $job) use ($schedule): bool {
            return $job->scheduleId === $schedule->id;
        });
        $this->assertDatabaseCount('kioku_letters', 0);
    }

    public function test_home_keeps_test_letters_out_of_live_frame(): void
    {
        $user = User::factory()->create();
        $live = KiokuLetter::factory()->daily('2026-07-15', 1)->create(['user_id' => $user->id]);
        $test = KiokuLetter::factory()->testMode()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('kioku.home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('letters', 1)
                ->where('letters.0.id', $live->id)
                ->has('testLetters', 1)
                ->where('testLetters.0.id', $test->id),
            );
    }
}
