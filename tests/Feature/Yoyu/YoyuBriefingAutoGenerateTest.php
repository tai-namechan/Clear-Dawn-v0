<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Yoyu\Jobs\GenerateYoyuBriefingJob;
use App\Domain\Yoyu\Models\YoyuBriefing;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class YoyuBriefingAutoGenerateTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_today_visit_creates_one_row_and_dispatches_once(): void
    {
        Bus::fake([GenerateYoyuBriefingJob::class]);
        $user = User::factory()->create();
        $tz = app(UserTimezoneResolver::class)->for($user);
        $today = CarbonImmutable::now($tz)->toDateString();

        $this->actingAs($user)->get(route('yoyu.home'))->assertOk();
        $this->actingAs($user)->get(route('yoyu.home'))->assertOk();

        $this->assertSame(1, YoyuBriefing::query()->where('user_id', $user->id)->whereDate('date', $today)->count());
        Bus::assertDispatchedTimes(GenerateYoyuBriefingJob::class, 1);
        Bus::assertDispatched(GenerateYoyuBriefingJob::class, function (GenerateYoyuBriefingJob $job) use ($today, $tz): bool {
            return $job->briefingDate === $today && $job->timezone === $tz;
        });
    }

    public function test_other_users_briefing_is_not_reused(): void
    {
        Bus::fake([GenerateYoyuBriefingJob::class]);
        $tz = 'Asia/Tokyo';
        config(['app.timezone' => $tz]);
        $today = CarbonImmutable::now($tz)->toDateString();

        $other = User::factory()->create();
        YoyuBriefing::query()->create([
            'user_id' => $other->id,
            'date' => $today,
            'body' => 'other',
            'status' => 'ready',
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('yoyu.home'))->assertOk();

        $this->assertSame(
            1,
            YoyuBriefing::query()->where('user_id', $user->id)->whereDate('date', $today)->count(),
        );
        $this->assertSame(
            2,
            YoyuBriefing::query()->withoutUserScope()->whereDate('date', $today)->count(),
        );
    }

    public function test_regenerate_keeps_old_body_and_structured_data(): void
    {
        Bus::fake([GenerateYoyuBriefingJob::class]);
        $user = User::factory()->create();
        $tz = app(UserTimezoneResolver::class)->for($user);
        $today = CarbonImmutable::now($tz)->toDateString();

        YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => $today,
            'body' => '保持本文',
            'structured_data' => [
                'schema_version' => 2,
                'generation' => ['status' => 'generated', 'overview' => '保持'],
            ],
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->post(route('yoyu.briefing.regenerate'))
            ->assertRedirect();

        $row = YoyuBriefing::query()->where('user_id', $user->id)->whereDate('date', $today)->first();
        $this->assertNotNull($row);
        $this->assertSame('generating', $row->status);
        $this->assertSame('保持本文', $row->body);
        $this->assertSame('保持', $row->structured_data['generation']['overview']);
        Bus::assertDispatched(GenerateYoyuBriefingJob::class);
    }
}
