<?php

namespace Tests\Feature\Ai;

use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\AiMoney;
use App\Domain\Shared\AI\AiUsageLedger;
use App\Domain\Shared\AI\AiUsagePeriodResolver;
use App\Domain\Shared\AI\PromptTemplate;
use App\Domain\Shared\AI\QuotaExceededException;
use App\Domain\Shared\Models\AiUsageLog;
use App\Domain\Shared\Models\AiUsageMonthly;
use App\Domain\Shared\Models\AiUsageRequest;
use App\Enums\AiUsageRequestStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiUsageLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_row_initializes_spent_from_existing_logs_once(): void
    {
        config(['app.timezone' => 'UTC']);
        $user = User::factory()->create();
        $period = now()->format('Y-m');

        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'estimated_cost_usd' => '1.2500',
            'created_at' => now(),
        ]);
        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'estimated_cost_usd' => '0.7500',
            'created_at' => now(),
        ]);
        AiUsageLog::factory()->create([
            'user_id' => User::factory()->create()->id,
            'estimated_cost_usd' => '9.0000',
            'created_at' => now(),
        ]);

        $ledger = app(AiUsageLedger::class);
        $monthly = $ledger->ensureMonthly($user->id, $period);

        $this->assertSame('2.000000', AiMoney::of((string) $monthly->spent_usd)->toString());
        $this->assertSame('0.000000', AiMoney::of((string) $monthly->reserved_usd)->toString());

        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'estimated_cost_usd' => '5.0000',
            'created_at' => now(),
        ]);

        $again = $ledger->ensureMonthly($user->id, $period);
        $this->assertSame($monthly->id, $again->id);
        $this->assertSame('2.000000', AiMoney::of((string) $again->fresh()->spent_usd)->toString());
    }

    public function test_reserve_allows_exact_limit_and_rejects_excess(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '1']);
        $user = User::factory()->create();
        $ledger = app(AiUsageLedger::class);

        $ok = $ledger->reserve($user->id, 'kioku.classify', 'claude-haiku-4-5-20251001', AiMoney::of('1'));
        $this->assertSame(AiUsageRequestStatus::Reserved, $ok->status);

        $monthly = AiUsageMonthly::query()->withoutUserScope()->where('user_id', $user->id)->first();
        $this->assertNotNull($monthly);
        $this->assertSame('1.000000', AiMoney::of((string) $monthly->reserved_usd)->toString());

        try {
            $ledger->reserve($user->id, 'kioku.classify', 'claude-haiku-4-5-20251001', AiMoney::of('0.000001'));
            $this->fail('Expected QuotaExceededException');
        } catch (QuotaExceededException) {
            $this->assertTrue(true);
        }

        $this->assertSame('1.000000', AiMoney::of((string) $monthly->fresh()->reserved_usd)->toString());
        $this->assertSame(1, AiUsageRequest::query()->withoutUserScope()->where('user_id', $user->id)->count());
    }

    public function test_multiple_reservations_cannot_exceed_limit(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '1']);
        $user = User::factory()->create();
        $ledger = app(AiUsageLedger::class);

        $ledger->reserve($user->id, 'a', 'claude-haiku-4-5-20251001', AiMoney::of('0.600000'));
        $ledger->reserve($user->id, 'b', 'claude-haiku-4-5-20251001', AiMoney::of('0.400000'));

        $this->expectException(QuotaExceededException::class);
        $ledger->reserve($user->id, 'c', 'claude-haiku-4-5-20251001', AiMoney::of('0.000001'));
    }

    public function test_conditional_update_reports_zero_affected_rows_when_over_limit(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '1']);
        $user = User::factory()->create();
        $period = app(AiUsagePeriodResolver::class)->periodFor();
        $ledger = app(AiUsageLedger::class);
        $ledger->ensureMonthly($user->id, $period);

        AiUsageMonthly::query()->withoutUserScope()->where('user_id', $user->id)->update([
            'spent_usd' => '0.900000',
            'reserved_usd' => '0.100000',
        ]);

        $affected = AiUsageMonthly::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('period', $period)
            ->whereRaw(
                'CAST(spent_usd AS DECIMAL(12,6)) + CAST(reserved_usd AS DECIMAL(12,6)) + CAST(? AS DECIMAL(12,6)) <= CAST(? AS DECIMAL(12,6))',
                ['0.000001', '1.000000'],
            )
            ->update(['reserved_usd' => '0.100001']);

        $this->assertSame(0, $affected);

        $affectedViaBinding = DB::update(
            'update ai_usage_monthly
             set updated_at = ?
             where user_id = ?
               and period = ?
               and CAST(spent_usd AS DECIMAL(12,6)) + CAST(reserved_usd AS DECIMAL(12,6)) + CAST(? AS DECIMAL(12,6))
                   <= CAST(? AS DECIMAL(12,6))',
            [now()->toDateTimeString(), $user->id, $period, '0.000001', '1.000000'],
        );

        $this->assertSame(0, $affectedViaBinding);
    }

    public function test_settle_moves_reserved_to_spent_and_writes_one_log(): void
    {
        $user = User::factory()->create();
        $ledger = app(AiUsageLedger::class);
        $request = $ledger->reserve($user->id, 'kioku.classify', 'claude-haiku-4-5-20251001', AiMoney::of('0.050000'));
        $ledger->markInFlight($request->id);

        $settled = $ledger->settle($request->id, AiMoney::of('0.012345'), 10, 20);

        $this->assertSame(AiUsageRequestStatus::Settled, $settled->status);
        $monthly = AiUsageMonthly::query()->withoutUserScope()->where('user_id', $user->id)->first();
        $this->assertNotNull($monthly);
        $this->assertSame('0.000000', AiMoney::of((string) $monthly->reserved_usd)->toString());
        $this->assertSame('0.012345', AiMoney::of((string) $monthly->spent_usd)->toString());
        $this->assertSame(1, AiUsageLog::query()->withoutUserScope()->where('usage_request_id', $request->id)->count());
    }

    public function test_settle_is_idempotent(): void
    {
        $user = User::factory()->create();
        $ledger = app(AiUsageLedger::class);
        $request = $ledger->reserve($user->id, 'kioku.classify', 'claude-haiku-4-5-20251001', AiMoney::of('0.050000'));
        $ledger->markInFlight($request->id);
        $ledger->settle($request->id, AiMoney::of('0.010000'), 1, 2);
        $ledger->settle($request->id, AiMoney::of('0.010000'), 1, 2);

        $monthly = AiUsageMonthly::query()->withoutUserScope()->where('user_id', $user->id)->first();
        $this->assertNotNull($monthly);
        $this->assertSame('0.010000', AiMoney::of((string) $monthly->spent_usd)->toString());
        $this->assertSame(1, AiUsageLog::query()->withoutUserScope()->where('user_id', $user->id)->count());
    }

    public function test_release_is_idempotent_and_settled_release_is_noop(): void
    {
        $user = User::factory()->create();
        $ledger = app(AiUsageLedger::class);
        $request = $ledger->reserve($user->id, 'kioku.classify', 'claude-haiku-4-5-20251001', AiMoney::of('0.050000'));

        $ledger->release($request->id, 'pre_http_failure');
        $ledger->release($request->id, 'pre_http_failure');

        $monthly = AiUsageMonthly::query()->withoutUserScope()->where('user_id', $user->id)->first();
        $this->assertNotNull($monthly);
        $this->assertSame('0.000000', AiMoney::of((string) $monthly->reserved_usd)->toString());
        $this->assertSame('0.000000', AiMoney::of((string) $monthly->spent_usd)->toString());

        $request2 = $ledger->reserve($user->id, 'kioku.classify', 'claude-haiku-4-5-20251001', AiMoney::of('0.050000'));
        $ledger->markInFlight($request2->id);
        $ledger->settle($request2->id, AiMoney::of('0.010000'), 1, 1);
        $before = $monthly->fresh();
        $ledger->release($request2->id, 'should_noop');
        $after = $monthly->fresh();

        $this->assertSame((string) $before->spent_usd, (string) $after->spent_usd);
        $this->assertSame((string) $before->reserved_usd, (string) $after->reserved_usd);
        $this->assertSame(AiUsageRequestStatus::Settled, $request2->fresh()->status);
    }

    public function test_stale_reserved_is_released_and_stale_in_flight_is_expired(): void
    {
        config(['ai.timeout' => 60]);
        $user = User::factory()->create();
        $ledger = app(AiUsageLedger::class);

        $reserved = $ledger->reserve($user->id, 'a', 'claude-haiku-4-5-20251001', AiMoney::of('0.100000'));
        AiUsageRequest::query()->withoutUserScope()->whereKey($reserved->id)->update([
            'created_at' => now()->subMinutes(11),
            'updated_at' => now()->subMinutes(11),
        ]);

        $inFlight = $ledger->reserve($user->id, 'b', 'claude-haiku-4-5-20251001', AiMoney::of('0.200000'));
        $ledger->markInFlight($inFlight->id);
        AiUsageRequest::query()->withoutUserScope()->whereKey($inFlight->id)->update([
            'provider_started_at' => now()->subSeconds(400),
            'updated_at' => now()->subSeconds(400),
        ]);

        Artisan::call('ai:usage-reap');
        Artisan::call('ai:usage-reap');

        $this->assertSame(AiUsageRequestStatus::Released, $reserved->fresh()->status);
        $this->assertSame(AiUsageRequestStatus::Expired, $inFlight->fresh()->status);

        $monthly = AiUsageMonthly::query()->withoutUserScope()->where('user_id', $user->id)->first();
        $this->assertNotNull($monthly);
        $this->assertSame('0.000000', AiMoney::of((string) $monthly->reserved_usd)->toString());
        $this->assertSame('0.200000', AiMoney::of((string) $monthly->spent_usd)->toString());
    }

    public function test_gateway_settle_keeps_actual_within_estimate(): void
    {
        config([
            'ai.anthropic.api_key' => 'test-key',
            'ai.models.cheap' => 'claude-haiku-4-5-20251001',
        ]);

        Http::fake([
            $this->anthropicFakePattern() => Http::response([
                'content' => [['type' => 'text', 'text' => 'ok']],
                'usage' => ['input_tokens' => 8, 'output_tokens' => 4],
            ]),
        ]);

        $user = User::factory()->create();
        $result = app(AiGateway::class)->complete(
            userId: $user->id,
            feature: 'kioku.classify',
            prompt: PromptTemplate::make('t', 'prefix', 'suffix'),
            tier: 'cheap',
            maxTokens: 40,
        );

        $request = AiUsageRequest::query()->withoutUserScope()->whereKey($result['usage_request_id'])->first();
        $this->assertNotNull($request);
        $this->assertSame(AiUsageRequestStatus::Settled, $request->status);
        $this->assertFalse(
            AiMoney::of((string) $request->actual_usd)->greaterThan(AiMoney::of((string) $request->estimated_usd))
        );
        $this->assertSame(1, AiUsageLog::query()->withoutUserScope()->where('usage_request_id', $request->id)->count());
    }

    public function test_jst_month_boundary_for_period_and_log_init(): void
    {
        config(['app.timezone' => 'Asia/Tokyo']);
        $resolver = app(AiUsagePeriodResolver::class);

        $justBefore = $resolver->periodFor(CarbonImmutable::parse('2026-06-30 14:59:59', 'UTC'));
        $justAfter = $resolver->periodFor(CarbonImmutable::parse('2026-06-30 15:00:00', 'UTC'));

        $this->assertSame('2026-06', $justBefore);
        $this->assertSame('2026-07', $justAfter);

        $user = User::factory()->create();
        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'estimated_cost_usd' => '1.0000',
            'created_at' => '2026-06-30 14:30:00',
        ]);
        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'estimated_cost_usd' => '2.0000',
            'created_at' => '2026-06-30 15:30:00',
        ]);

        $ledger = app(AiUsageLedger::class);
        $june = $ledger->ensureMonthly($user->id, '2026-06');
        $july = $ledger->ensureMonthly($user->id, '2026-07');

        $this->assertSame('1.000000', AiMoney::of((string) $june->spent_usd)->toString());
        $this->assertSame('2.000000', AiMoney::of((string) $july->spent_usd)->toString());
    }

    public function test_other_users_monthly_and_requests_are_not_visible_via_user_scope(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ledger = app(AiUsageLedger::class);
        $ledger->reserve($other->id, 'secret', 'claude-haiku-4-5-20251001', AiMoney::of('0.010000'));

        $this->actingAs($user);

        $this->assertSame(0, AiUsageMonthly::query()->count());
        $this->assertSame(0, AiUsageRequest::query()->count());
        $this->assertSame(1, AiUsageMonthly::query()->withoutUserScope()->where('user_id', $other->id)->count());
    }

    public function test_quota_exceeded_does_not_block_non_ai_memory_store(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '0.000001']);
        $user = User::factory()->create();
        $ledger = app(AiUsageLedger::class);
        $ledger->reserve($user->id, 'fill', 'claude-haiku-4-5-20251001', AiMoney::of('0.000001'));

        Bus::fake();

        $this->actingAs($user)
            ->post(route('kioku.memories.store'), [
                'raw_content' => '上限でも原文は保存できる',
            ])
            ->assertRedirect(route('kioku.home'));

        $this->assertDatabaseHas('memories', [
            'user_id' => $user->id,
            'raw_content' => '上限でも原文は保存できる',
        ]);
    }
}
