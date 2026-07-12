<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Yoyu\Jobs\GenerateYoyuBriefingJob;
use App\Domain\Yoyu\Models\YoyuBriefing;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class YoyuBriefingStructuredPropsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([GenerateYoyuBriefingJob::class]);
    }

    public function test_v2_structured_briefing_is_shared_to_inertia(): void
    {
        $user = User::factory()->create();
        $today = $this->todayFor($user);
        YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => $today,
            'body' => 'legacy body',
            'status' => 'ready',
            'structured_data' => $this->v2Payload([
                'generation' => [
                    'status' => 'generated',
                    'overview' => '全体像テキスト',
                    'caution' => null,
                    'hand_note' => null,
                    'gap_suggestions' => [],
                    'let_go' => '手放す',
                    'pattern_note' => null,
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('structuredBriefing.schema_version', 2)
                ->where('structuredBriefing.generation.overview', '全体像テキスト')
                ->where('briefing', 'legacy body')
                ->where('briefingStatus', 'ready')
            );
    }

    public function test_generating_keeps_old_structured_data_visible(): void
    {
        $user = User::factory()->create();
        $today = $this->todayFor($user);
        YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => $today,
            'body' => '旧本文',
            'status' => 'generating',
            'structured_data' => $this->v2Payload([
                'generation' => [
                    'status' => 'generated',
                    'overview' => '旧overview',
                    'caution' => null,
                    'hand_note' => null,
                    'gap_suggestions' => [],
                    'let_go' => '旧',
                    'pattern_note' => null,
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('briefingStatus', 'generating')
                ->where('structuredBriefing.generation.overview', '旧overview')
                ->where('briefing', '旧本文')
            );
    }

    public function test_quota_limited_and_invalid_response_props(): void
    {
        $user = User::factory()->create();
        $today = $this->todayFor($user);
        YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => $today,
            'body' => 'quota body',
            'status' => 'ready',
            'structured_data' => $this->v2Payload([
                'generation' => [
                    'status' => 'quota_limited',
                    'overview' => null,
                    'caution' => null,
                    'hand_note' => null,
                    'gap_suggestions' => [],
                    'let_go' => null,
                    'pattern_note' => null,
                ],
                'analysis' => [
                    'busy_minutes' => 30,
                    'task_minutes' => 0,
                    'working_minutes' => 960,
                    'margin_score' => 97,
                    'margin_label' => 'ゆったり',
                    'gaps' => [
                        ['key' => 'gap_1', 'start' => '10:00', 'end' => '11:00', 'minutes' => 60],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('structuredBriefing.generation.status', 'quota_limited')
                ->where('structuredBriefing.analysis.margin_score', 97)
                ->has('structuredBriefing.analysis.gaps', 1)
            );
    }

    public function test_legacy_body_fallback_when_structured_missing(): void
    {
        $user = User::factory()->create();
        $today = $this->todayFor($user);
        YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => $today,
            'body' => 'レガシー本文のみ',
            'status' => 'ready',
            'structured_data' => null,
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('briefing', 'レガシー本文のみ')
                ->where('structuredBriefing', null)
            );
    }

    public function test_stale_and_disconnected_connection_props_remain(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('yoyu.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('calendarConnection.status')
                ->has('calendarConnection.warning_code')
                ->has('calendarConnection.is_stale')
                ->where('calendar', [])
            );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function v2Payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'schema_version' => 2,
            'briefing_date' => '2026-07-11',
            'timezone' => 'Asia/Tokyo',
            'calendar' => [
                'connection_status' => 'disconnected',
                'synced_at' => null,
                'is_stale' => false,
                'warning_code' => 'disconnected',
            ],
            'analysis' => [
                'busy_minutes' => 0,
                'task_minutes' => 0,
                'working_minutes' => 960,
                'margin_score' => 100,
                'margin_label' => 'ゆったり',
                'gaps' => [],
            ],
            'hand' => null,
            'generation' => [
                'status' => 'generated',
                'overview' => null,
                'caution' => null,
                'hand_note' => null,
                'gap_suggestions' => [],
                'let_go' => null,
                'pattern_note' => null,
            ],
        ], $overrides);
    }

    private function todayFor(User $user): string
    {
        $tz = app(UserTimezoneResolver::class)->for($user);

        return CarbonImmutable::now($tz)->toDateString();
    }
}
