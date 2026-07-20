<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Yoyu\Jobs\GenerateYoyuBriefingJob;
use App\Domain\Yoyu\Models\YoyuBriefing;
use App\Domain\Yoyu\Models\YoyuTask;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class YoyuHomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([GenerateYoyuBriefingJob::class]);
    }

    public function test_guests_are_redirected_from_yoyu(): void
    {
        $this->get(route('yoyu.home'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_yoyu_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Yoyu/Index')
                ->where('currentProduct', 'yoyu')
                ->has('calendar')
                ->has('analysis')
                ->has('travelLead')
                ->has('structuredBriefing')
                ->where('clearDawnHand', null)
            );
    }

    public function test_tab_switch_partial_reload_skips_heavy_props_and_keeps_chat_session(): void
    {
        $user = User::factory()->create();

        // フル訪問（全 props が入る）
        $this->actingAs($user)->get(route('yoyu.home'))->assertOk();

        // タブ切替は only=['tab'] の partial reload（サイドバーの実装と同じヘッダ）
        session(['chat_reply' => '残しておく返信']);

        $response = $this->actingAs($user)->get(route('yoyu.home', ['tab' => 'tasks']), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '',
            'X-Inertia-Partial-Component' => 'Yoyu/Index',
            'X-Inertia-Partial-Data' => 'tab',
        ]);

        $response->assertOk();
        $props = $response->json('props');

        $this->assertSame('tasks', $props['tab']);
        $this->assertArrayNotHasKey('calendar', $props);
        $this->assertArrayNotHasKey('briefing', $props);
        $this->assertArrayNotHasKey('recallPreview', $props);

        // 遅延化した session pull が partial reload で評価されず、値が破棄されないこと
        $this->assertSame('残しておく返信', session('chat_reply'));
    }

    public function test_user_can_create_task(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('yoyu.tasks.store'), [
                'title' => 'READMEを直す',
                'estimate_minutes' => 30,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('yoyu_tasks', [
            'user_id' => $user->id,
            'title' => 'READMEを直す',
            'status' => 'planned',
        ]);
    }

    public function test_mind_dump_creates_memory_and_focus_item(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('yoyu.focus.store'), [
                'text' => '頭の中のモヤモヤ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('memories', [
            'user_id' => $user->id,
            'raw_content' => '頭の中のモヤモヤ',
            'source_type' => 'yoyu',
        ]);
        $this->assertDatabaseCount('yoyu_focus_items', 1);
        Bus::assertDispatched(EnrichMemoryJob::class);
    }

    public function test_user_cannot_update_another_users_task(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = YoyuTask::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->patch(route('yoyu.tasks.update', $task), ['status' => 'done'])
            ->assertNotFound();
    }

    public function test_regenerate_briefing_dispatches_job_and_sets_generating_status(): void
    {
        Bus::fake([GenerateYoyuBriefingJob::class]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('yoyu.briefing.regenerate'))
            ->assertRedirect(route('yoyu.home', ['tab' => 'today']));

        $this->assertDatabaseHas('yoyu_briefings', [
            'user_id' => $user->id,
            'status' => 'generating',
        ]);

        // Queued (not afterResponse/dispatchSync) so AI runs on a worker.
        Bus::assertDispatched(GenerateYoyuBriefingJob::class, function (GenerateYoyuBriefingJob $job) use ($user): bool {
            $tz = app(UserTimezoneResolver::class)->for($user);

            return $job->timezone === $tz
                && $job->briefingDate === CarbonImmutable::now($tz)->toDateString()
                && $job->briefingId !== '';
        });
        Bus::assertNotDispatchedSync(GenerateYoyuBriefingJob::class);
    }

    public function test_yoyu_home_shares_briefing_status(): void
    {
        $user = User::factory()->create();
        YoyuBriefing::query()->create([
            'user_id' => $user->id,
            // ブリーフィングの「今日」はユーザーTZ（S-02）。サーバーTZの today() だと
            // JST の日付が変わった後（UTC 15時以降）の実行で日付がずれて flaky になる。
            'date' => app(UserTimezoneResolver::class)->todayDateString($user),
            'body' => 'テストブリーフィング',
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('briefing', 'テストブリーフィング')
                ->where('briefingStatus', 'ready')
            );
    }
}
