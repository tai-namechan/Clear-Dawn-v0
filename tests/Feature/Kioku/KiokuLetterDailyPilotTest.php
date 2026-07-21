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
        $this->assertNotNull($schedule->next_delivery_at);

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
        $schedule->refresh();
        $this->assertSame(KiokuConciergeScheduleState::Completed->value, $schedule->state);
        $this->assertNull($schedule->next_delivery_at);
        $this->assertSame('final pilot day delivered', $schedule->pause_reason);

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
                ->component('Kioku/Index')
                ->has('letters', 1)
                ->where('letters.0.id', $live->id)
                ->missing('testLetters'),
            );

        $this->actingAs($user)
            ->get(route('kioku.letters.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Kioku/Letters')
                ->has('letters', 1)
                ->where('letters.0.id', $live->id)
                ->has('testLetters', 1)
                ->where('testLetters.0.id', $test->id),
            );
    }

    public function test_start_stores_tokyo_2100_as_utc_and_due_path_fires_day_one(): void
    {
        $user = User::factory()->create();
        $this->readyMemory($user);
        $this->fakeLetterResponse([]);

        // Before local delivery time on day 1.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-15 10:00:00', 'Asia/Tokyo')->utc());

        $this->artisan('kioku:letters:pilot:start', [
            'userId' => $user->id,
            '--start' => '2026-07-15',
            '--days' => 14,
            '--time' => '21:00',
            '--timezone' => 'Asia/Tokyo',
        ])->assertSuccessful();

        $schedule = KiokuConciergeSchedule::query()->withoutUserScope()->where('user_id', $user->id)->sole();
        $this->assertSame(KiokuConciergeScheduleState::Active->value, $schedule->state);
        $this->assertNotNull($schedule->next_delivery_at);
        // Asia/Tokyo 21:00 on 2026-07-15 → 12:00 UTC same calendar day.
        $this->assertSame(
            '2026-07-15 12:00:00',
            $schedule->next_delivery_at->clone()->utc()->format('Y-m-d H:i:s'),
        );

        $pilot = app(KiokuConciergePilotService::class);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-15 11:59:59', 'UTC'));
        $this->assertCount(0, $pilot->dueSchedules(CarbonImmutable::now('UTC')));

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-15 12:00:00', 'UTC'));
        $due = $pilot->dueSchedules(CarbonImmutable::now('UTC'));
        $this->assertCount(1, $due);
        $this->assertSame($schedule->id, $due->first()->id);

        $letter = $pilot->deliverForSchedule($schedule->fresh());
        $this->assertNotNull($letter);
        $this->assertSame(1, $letter->pilot_day);
        $this->assertSame('2026-07-15', $letter->delivery_date->toDateString());

        CarbonImmutable::setTestNow();
    }

    public function test_start_timezone_america_los_angeles_converts_to_utc(): void
    {
        $user = User::factory()->create();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-15 10:00:00', 'America/Los_Angeles')->utc());

        $this->artisan('kioku:letters:pilot:start', [
            'userId' => $user->id,
            '--start' => '2026-07-15',
            '--days' => 7,
            '--time' => '21:00',
            '--timezone' => 'America/Los_Angeles',
        ])->assertSuccessful();

        $schedule = KiokuConciergeSchedule::query()->withoutUserScope()->where('user_id', $user->id)->sole();
        // 2026-07-15 21:00 America/Los_Angeles (PDT) → 2026-07-16 04:00 UTC.
        $this->assertSame(
            '2026-07-16 04:00:00',
            $schedule->next_delivery_at->clone()->utc()->format('Y-m-d H:i:s'),
        );

        CarbonImmutable::setTestNow();
    }

    public function test_send_now_start_still_delivers_day_one_and_advances_next_utc(): void
    {
        $user = User::factory()->create();
        $this->readyMemory($user);
        $this->fakeLetterResponse([]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-15 10:00:00', 'Asia/Tokyo')->utc());

        $result = app(KiokuConciergePilotService::class)->start(
            $user,
            CarbonImmutable::parse('2026-07-15'),
            14,
            '21:00',
            'Asia/Tokyo',
            sendNow: true,
            dryRun: false,
        );

        $this->assertNotNull($result['letter']);
        $this->assertSame(1, $result['letter']->pilot_day);
        $this->assertSame('2026-07-15', $result['letter']->delivery_date->toDateString());

        $schedule = $result['schedule'];
        $this->assertSame(KiokuConciergeScheduleState::Active->value, $schedule->state);
        // After day-1 send-now, next slot is day 2 21:00 JST = 2026-07-16 12:00 UTC.
        $this->assertSame(
            '2026-07-16 12:00:00',
            $schedule->next_delivery_at->clone()->utc()->format('Y-m-d H:i:s'),
        );

        CarbonImmutable::setTestNow();
    }

    public function test_sensitive_leak_halt_resolve_restores_utc_next_and_due_dispatch(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $memory = $this->readyMemory($user);
        $this->fakeLetterResponse([]);

        // Day 1 morning: start pilot, then deliver day 1 via due path.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-15 10:00:00', 'Asia/Tokyo')->utc());
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

        $otherSchedule = KiokuConciergeSchedule::factory()->active()->create([
            'user_id' => $other->id,
            'next_delivery_at' => CarbonImmutable::parse('2026-07-15 12:00:00', 'UTC'),
        ]);
        $otherNext = $otherSchedule->next_delivery_at->clone()->utc()->format('Y-m-d H:i:s');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-15 12:00:00', 'UTC'));
        $letter = $pilot->deliverForSchedule(
            KiokuConciergeSchedule::query()->withoutUserScope()->where('user_id', $user->id)->sole(),
        );
        $this->assertNotNull($letter);

        // Attach a real item so applySensitiveHalt runs through the HTTP verdict path.
        $item = KiokuLetterItem::factory()->create([
            'letter_id' => $letter->id,
            'memory_id' => $memory->id,
            'position' => 1,
        ]);
        $letter->update(['item_count' => 1, 'status' => KiokuLetter::STATUS_PUBLISHED]);

        $this->actingAs($user)->put(
            route('kioku.letters.items.verdict', [$letter, $item]),
            ['verdict' => KiokuLetterItem::VERDICT_SENSITIVE_LEAK],
        );

        $schedule = KiokuConciergeSchedule::query()->withoutUserScope()->where('user_id', $user->id)->sole();
        $this->assertSame(KiokuConciergeScheduleState::Halted->value, $schedule->state);
        $this->assertNull($schedule->next_delivery_at);
        $this->assertTrue($memory->fresh()->sensitive);
        $this->assertSame(KiokuLetter::STATUS_HALTED, $letter->fresh()->status);

        // Other user's schedule is untouched.
        $otherSchedule->refresh();
        $this->assertSame(KiokuConciergeScheduleState::Active->value, $otherSchedule->state);
        $this->assertSame($otherNext, $otherSchedule->next_delivery_at->clone()->utc()->format('Y-m-d H:i:s'));

        // Resolve next morning (before 21:00 JST) → next slot is today 21:00 JST = 12:00 UTC.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-16 10:00:00', 'Asia/Tokyo')->utc());
        $this->artisan('kioku:letters:resolve-halt', [
            'userId' => $user->id,
            'letterId' => $letter->id,
            '--note' => '隔離を維持して再開',
        ])->assertSuccessful();

        $schedule->refresh();
        $this->assertSame(KiokuConciergeScheduleState::Active->value, $schedule->state);
        $this->assertNotNull($schedule->next_delivery_at);
        $this->assertSame(
            '2026-07-16 12:00:00',
            $schedule->next_delivery_at->clone()->utc()->format('Y-m-d H:i:s'),
        );

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-16 11:59:59', 'UTC'));
        $this->assertFalse(
            $pilot->dueSchedules(CarbonImmutable::now('UTC'))->contains('id', $schedule->id),
        );

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-16 12:00:00', 'UTC'));
        $this->assertTrue(
            $pilot->dueSchedules(CarbonImmutable::now('UTC'))->contains('id', $schedule->id),
        );

        // Idempotent resolve: second call keeps the same next_delivery_at.
        $nextBefore = $schedule->next_delivery_at->clone()->utc()->format('Y-m-d H:i:s');
        $this->artisan('kioku:letters:resolve-halt', [
            'userId' => $user->id,
            'letterId' => $letter->id,
            '--note' => '再実行',
        ])->assertSuccessful();
        $this->assertSame(
            $nextBefore,
            $schedule->fresh()->next_delivery_at->clone()->utc()->format('Y-m-d H:i:s'),
        );

        CarbonImmutable::setTestNow();
    }

    public function test_resolve_halt_on_expired_pilot_completes_without_next(): void
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

        KiokuConciergeSchedule::factory()->active('2026-07-01', 3)->create([
            'user_id' => $user->id,
            'state' => KiokuConciergeScheduleState::Halted->value,
            'pause_reason' => 'sensitive_leak',
            'next_delivery_at' => null,
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-10 12:00:00', 'Asia/Tokyo')->utc());

        $this->artisan('kioku:letters:resolve-halt', [
            'userId' => $user->id,
            'letterId' => $letter->id,
            '--note' => '期限切れ後の解消',
        ])->assertSuccessful();

        $schedule = KiokuConciergeSchedule::query()->withoutUserScope()->where('user_id', $user->id)->sole();
        $this->assertSame(KiokuConciergeScheduleState::Completed->value, $schedule->state);
        $this->assertNull($schedule->next_delivery_at);

        CarbonImmutable::setTestNow();
    }

    public function test_resolve_halt_does_not_resume_when_another_unresolved_halt_remains(): void
    {
        $user = User::factory()->create();
        $memoryA = $this->readyMemory($user);
        $memoryB = $this->readyMemory($user, ['title' => '別リーク']);

        $letterA = KiokuLetter::factory()->daily('2026-07-15', 1)->create([
            'user_id' => $user->id,
            'item_count' => 1,
            'status' => KiokuLetter::STATUS_HALTED,
            'halted_at' => now(),
        ]);
        KiokuLetterItem::factory()->create([
            'letter_id' => $letterA->id,
            'memory_id' => $memoryA->id,
            'position' => 1,
            'verdict' => KiokuLetterItem::VERDICT_SENSITIVE_LEAK,
            'verdict_at' => now(),
        ]);
        $memoryA->update(['sensitive' => true]);

        $letterB = KiokuLetter::factory()->daily('2026-07-16', 2)->create([
            'user_id' => $user->id,
            'item_count' => 1,
            'status' => KiokuLetter::STATUS_HALTED,
            'halted_at' => now(),
        ]);
        KiokuLetterItem::factory()->create([
            'letter_id' => $letterB->id,
            'memory_id' => $memoryB->id,
            'position' => 1,
            'verdict' => KiokuLetterItem::VERDICT_SENSITIVE_LEAK,
            'verdict_at' => now(),
        ]);
        $memoryB->update(['sensitive' => true]);

        KiokuConciergeSchedule::factory()->active()->create([
            'user_id' => $user->id,
            'state' => KiokuConciergeScheduleState::Halted->value,
            'pause_reason' => 'sensitive_leak',
            'next_delivery_at' => null,
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-17 10:00:00', 'Asia/Tokyo')->utc());

        $this->artisan('kioku:letters:resolve-halt', [
            'userId' => $user->id,
            'letterId' => $letterA->id,
            '--note' => '片方だけ解消',
        ])->assertSuccessful();

        $schedule = KiokuConciergeSchedule::query()->withoutUserScope()->where('user_id', $user->id)->sole();
        $this->assertSame(KiokuConciergeScheduleState::Halted->value, $schedule->state);
        $this->assertNull($schedule->next_delivery_at);
        $this->assertTrue(app(KiokuLetterHaltGuard::class)->hasUnresolvedHalt((int) $user->id));

        CarbonImmutable::setTestNow();
    }

    public function test_manual_resume_before_delivery_time_sets_today_utc_next(): void
    {
        $user = User::factory()->create();
        $schedule = KiokuConciergeSchedule::factory()->active('2026-07-15', 14)->create([
            'user_id' => $user->id,
            'state' => KiokuConciergeScheduleState::Paused->value,
            'pause_reason' => 'manual pause',
            'next_delivery_at' => null,
        ]);

        // 10:00 JST → next is today 21:00 JST = 12:00 UTC.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-16 10:00:00', 'Asia/Tokyo')->utc());

        $this->artisan('kioku:letters:pilot:resume', [
            'userId' => $user->id,
            '--note' => '午前再開',
        ])->assertSuccessful();

        $schedule->refresh();
        $this->assertSame(KiokuConciergeScheduleState::Active->value, $schedule->state);
        $this->assertNotNull($schedule->next_delivery_at);
        $this->assertSame(
            '2026-07-16 12:00:00',
            $schedule->next_delivery_at->clone()->utc()->format('Y-m-d H:i:s'),
        );
        $this->assertSame('resumed: 午前再開', $schedule->pause_reason);
        $this->assertSame(0, $schedule->consecutive_unopened);

        CarbonImmutable::setTestNow();
    }

    public function test_manual_resume_after_delivery_time_sets_tomorrow_utc_next(): void
    {
        $user = User::factory()->create();
        $schedule = KiokuConciergeSchedule::factory()->active('2026-07-15', 14)->create([
            'user_id' => $user->id,
            'state' => KiokuConciergeScheduleState::Paused->value,
            'pause_reason' => 'manual pause',
            'next_delivery_at' => null,
        ]);

        // 22:00 JST → past 21:00 → tomorrow 21:00 JST = 2026-07-17 12:00 UTC.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-16 22:00:00', 'Asia/Tokyo')->utc());

        $resumed = app(KiokuConciergePilotService::class)->resume($user, '夜再開');

        $this->assertSame(KiokuConciergeScheduleState::Active->value, $resumed->state);
        $this->assertNotNull($resumed->next_delivery_at);
        $this->assertSame(
            '2026-07-17 12:00:00',
            $resumed->next_delivery_at->clone()->utc()->format('Y-m-d H:i:s'),
        );

        CarbonImmutable::setTestNow();
    }

    public function test_final_day_existing_letter_recovery_completes_without_ai(): void
    {
        $user = User::factory()->create();
        $existing = KiokuLetter::factory()->daily('2026-07-28', 14)->create([
            'user_id' => $user->id,
            'status' => KiokuLetter::STATUS_PUBLISHED,
        ]);

        $schedule = KiokuConciergeSchedule::factory()->active('2026-07-15', 14)->create([
            'user_id' => $user->id,
            'next_delivery_at' => CarbonImmutable::parse('2026-07-28 12:00:00', 'UTC'),
        ]);

        Http::fake();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-28 21:00:00', 'Asia/Tokyo')->utc());

        $returned = app(KiokuConciergePilotService::class)->deliverForSchedule($schedule);
        $this->assertNotNull($returned);
        $this->assertSame($existing->id, $returned->id);

        $schedule->refresh();
        $this->assertSame(KiokuConciergeScheduleState::Completed->value, $schedule->state);
        $this->assertNull($schedule->next_delivery_at);
        $this->assertSame('final pilot day delivered', $schedule->pause_reason);

        Http::assertNothingSent();
        $this->assertSame(
            1,
            KiokuLetter::query()->withoutUserScope()->where('user_id', $user->id)->where('cadence', 'daily')->count(),
        );

        CarbonImmutable::setTestNow();
    }
}
