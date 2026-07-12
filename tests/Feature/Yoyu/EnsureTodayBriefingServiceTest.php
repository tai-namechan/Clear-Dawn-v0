<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Yoyu\Jobs\GenerateYoyuBriefingJob;
use App\Domain\Yoyu\Models\YoyuBriefing;
use App\Domain\Yoyu\Services\EnsureTodayBriefingService;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use ReflectionMethod;
use Tests\TestCase;

class EnsureTodayBriefingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_twice_creates_one_row_and_dispatches_once(): void
    {
        Bus::fake([GenerateYoyuBriefingJob::class]);
        $user = User::factory()->create();
        $service = app(EnsureTodayBriefingService::class);

        $first = $service->ensure($user);
        $second = $service->ensure($user);

        $tz = app(UserTimezoneResolver::class)->for($user);
        $today = CarbonImmutable::now($tz)->toDateString();

        $this->assertTrue($first['dispatched']);
        $this->assertFalse($second['dispatched']);
        $this->assertSame($first['briefing']->id, $second['briefing']->id);
        $this->assertSame(1, YoyuBriefing::query()->where('user_id', $user->id)->whereDate('date', $today)->count());
        Bus::assertDispatchedTimes(GenerateYoyuBriefingJob::class, 1);
        Bus::assertDispatched(GenerateYoyuBriefingJob::class, function (GenerateYoyuBriefingJob $job) use ($first): bool {
            $generationId = (string) $first['briefing']->generation_id;

            return $job->briefingId === $first['briefing']->id
                && $job->generationId === $generationId
                && $job->uniqueId() === $first['briefing']->id.':'.$generationId
                && $job->uniqueFor === 7200;
        });
    }

    public function test_regenerate_assigns_generation_id_when_pre_migration_row_has_null(): void
    {
        Bus::fake([GenerateYoyuBriefingJob::class]);
        $user = User::factory()->create();
        $tz = app(UserTimezoneResolver::class)->for($user);
        $today = CarbonImmutable::now($tz)->toDateString();

        $row = YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => $today,
            'body' => 'pre-migration body',
            'structured_data' => ['schema_version' => 2, 'generation' => ['overview' => '旧']],
            'status' => 'ready',
            'generation_id' => null,
        ]);
        $this->assertNull($row->fresh()->generation_id);

        $result = app(EnsureTodayBriefingService::class)->regenerate($user);

        $this->assertTrue($result['dispatched']);
        $this->assertNotEmpty($result['briefing']->generation_id);
        $this->assertSame('pre-migration body', $result['briefing']->body);
        Bus::assertDispatched(GenerateYoyuBriefingJob::class, function (GenerateYoyuBriefingJob $job) use ($result): bool {
            return $job->briefingId === $result['briefing']->id
                && $job->generationId === (string) $result['briefing']->generation_id
                && $job->uniqueId() === $result['briefing']->id.':'.$result['briefing']->generation_id;
        });
    }

    public function test_regenerate_without_row_creates_and_dispatches(): void
    {
        Bus::fake([GenerateYoyuBriefingJob::class]);
        $user = User::factory()->create();

        $result = app(EnsureTodayBriefingService::class)->regenerate($user);

        $this->assertTrue($result['dispatched']);
        $this->assertFalse($result['already_running']);
        $this->assertSame('generating', $result['briefing']->status);
        $this->assertNotEmpty($result['briefing']->generation_id);
        Bus::assertDispatchedTimes(GenerateYoyuBriefingJob::class, 1);
    }

    public function test_regenerate_while_pending_is_already_running(): void
    {
        Bus::fake([GenerateYoyuBriefingJob::class]);
        $user = User::factory()->create();
        $tz = app(UserTimezoneResolver::class)->for($user);
        $today = CarbonImmutable::now($tz)->toDateString();

        YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => $today,
            'body' => 'pending body',
            'status' => 'pending',
            'generation_id' => 'gen-pending',
        ]);

        $result = app(EnsureTodayBriefingService::class)->regenerate($user);

        $this->assertTrue($result['already_running']);
        $this->assertFalse($result['dispatched']);
        Bus::assertNotDispatched(GenerateYoyuBriefingJob::class);
    }

    public function test_regenerate_while_generating_is_already_running(): void
    {
        Bus::fake([GenerateYoyuBriefingJob::class]);
        $user = User::factory()->create();
        $tz = app(UserTimezoneResolver::class)->for($user);
        $today = CarbonImmutable::now($tz)->toDateString();

        YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => $today,
            'body' => 'generating body',
            'structured_data' => ['schema_version' => 2, 'generation' => ['overview' => 'old']],
            'status' => 'generating',
            'generation_id' => 'gen-generating',
        ]);

        $result = app(EnsureTodayBriefingService::class)->regenerate($user);

        $this->assertTrue($result['already_running']);
        $this->assertFalse($result['dispatched']);
        $this->assertSame('old', $result['briefing']->fresh()->structured_data['generation']['overview']);
        Bus::assertNotDispatched(GenerateYoyuBriefingJob::class);
    }

    public function test_regenerate_from_ready_dispatches_once_and_keeps_body(): void
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
            'generation_id' => 'gen-ready',
        ]);

        $result = app(EnsureTodayBriefingService::class)->regenerate($user);

        $this->assertTrue($result['dispatched']);
        $this->assertFalse($result['already_running']);
        $this->assertSame('generating', $result['briefing']->status);
        $this->assertSame('保持本文', $result['briefing']->body);
        $this->assertSame('保持', $result['briefing']->structured_data['generation']['overview']);
        $this->assertNotSame('gen-ready', $result['briefing']->generation_id);
        Bus::assertDispatchedTimes(GenerateYoyuBriefingJob::class, 1);
    }

    public function test_regenerate_from_failed_dispatches_once(): void
    {
        Bus::fake([GenerateYoyuBriefingJob::class]);
        $user = User::factory()->create();
        $tz = app(UserTimezoneResolver::class)->for($user);
        $today = CarbonImmutable::now($tz)->toDateString();

        YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => $today,
            'body' => 'failed body',
            'status' => 'failed',
            'generation_id' => 'gen-failed',
        ]);

        $result = app(EnsureTodayBriefingService::class)->regenerate($user);

        $this->assertTrue($result['dispatched']);
        $this->assertFalse($result['already_running']);
        $this->assertSame('generating', $result['briefing']->status);
        Bus::assertDispatchedTimes(GenerateYoyuBriefingJob::class, 1);
    }

    public function test_controller_regenerate_already_running_does_not_dispatch(): void
    {
        Bus::fake([GenerateYoyuBriefingJob::class]);
        $user = User::factory()->create();
        $tz = app(UserTimezoneResolver::class)->for($user);
        $today = CarbonImmutable::now($tz)->toDateString();

        YoyuBriefing::query()->create([
            'user_id' => $user->id,
            'date' => $today,
            'body' => 'busy',
            'status' => 'generating',
            'generation_id' => 'gen-busy',
        ]);

        $this->actingAs($user)
            ->post(route('yoyu.briefing.regenerate'))
            ->assertRedirect(route('yoyu.home', ['tab' => 'today']));

        Bus::assertNotDispatched(GenerateYoyuBriefingJob::class);
    }

    public function test_is_unique_violation_rejects_unrelated_query_exceptions(): void
    {
        $service = app(EnsureTodayBriefingService::class);
        $method = new ReflectionMethod(EnsureTodayBriefingService::class, 'isUniqueViolation');

        $unique = new UniqueConstraintViolationException(
            'sqlite',
            'insert',
            [],
            new \Exception('UNIQUE constraint failed'),
        );
        $this->assertTrue($method->invoke($service, $unique));

        $unrelated = new QueryException(
            'sqlite',
            'insert into missing',
            [],
            new \Exception('SQLSTATE[HY000]: General error: 1 no such table: yoyu_briefings'),
        );
        $this->assertFalse($method->invoke($service, $unrelated));
    }
}
