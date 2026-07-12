<?php

namespace Tests\Feature\Ai;

use App\Domain\Shared\AI\AiMoney;
use App\Domain\Shared\AI\AiUsageLedger;
use App\Domain\Shared\AI\AiUsageReconciler;
use App\Domain\Shared\AI\AiUsageSummary;
use App\Domain\Shared\Models\AiUsageLog;
use App\Domain\Shared\Models\AiUsageMonthly;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AiUsageCanonicalSpentTest extends TestCase
{
    use RefreshDatabase;

    public function test_sub_four_digit_settled_charge_survives_reconcile(): void
    {
        config(['app.timezone' => 'UTC', 'ai.limits.monthly_usd_per_user' => '10']);
        $user = User::factory()->create();
        $period = now()->format('Y-m');
        $ledger = app(AiUsageLedger::class);

        $request = $ledger->reserve(
            $user->id,
            'kioku.classify',
            'claude-haiku-4-5-20251001',
            AiMoney::of('0.010000'),
        );
        $ledger->markInFlight($request->id);
        $ledger->settle($request->id, AiMoney::of('0.000037'), 3, 1);

        $monthly = AiUsageMonthly::query()->withoutUserScope()->where('user_id', $user->id)->first();
        $this->assertNotNull($monthly);
        $this->assertSame('0.000037', AiMoney::of((string) $monthly->spent_usd)->toString());

        Artisan::call('ai:usage-reconcile', ['--period' => $period]);
        $this->assertSame('0.000037', AiMoney::of((string) $monthly->fresh()->spent_usd)->toString());

        Artisan::call('ai:usage-reconcile', ['--period' => $period]);
        $this->assertSame('0.000037', AiMoney::of((string) $monthly->fresh()->spent_usd)->toString());
        $this->assertSame(0, app(AiUsageReconciler::class)->reconcilePeriod($period)['adjusted']);
    }

    public function test_request_linked_logs_are_not_double_counted(): void
    {
        config(['app.timezone' => 'UTC']);
        $user = User::factory()->create();
        $period = now()->format('Y-m');
        $ledger = app(AiUsageLedger::class);

        $request = $ledger->reserve($user->id, 'kioku.classify', 'claude-haiku-4-5-20251001', AiMoney::of('0.010000'));
        $ledger->markInFlight($request->id);
        $ledger->settle($request->id, AiMoney::of('0.000037'), 3, 1);

        // Linked log exists from settle; canonical spent must remain charged_usd only once.
        $this->assertSame(1, AiUsageLog::query()->withoutUserScope()->where('usage_request_id', $request->id)->count());
        $this->assertSame('0.000037', $ledger->sumCanonicalSpent($user->id, $period)->toString());
    }

    public function test_legacy_null_request_logs_are_included(): void
    {
        config(['app.timezone' => 'UTC']);
        $user = User::factory()->create();
        $period = now()->format('Y-m');

        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'usage_request_id' => null,
            'estimated_cost_usd' => '1.250000',
            'created_at' => now(),
        ]);

        $ledger = app(AiUsageLedger::class);
        $this->assertSame('1.250000', $ledger->sumCanonicalSpent($user->id, $period)->toString());

        $monthly = $ledger->ensureMonthly($user->id, $period);
        $this->assertSame('1.250000', AiMoney::of((string) $monthly->spent_usd)->toString());
    }

    public function test_settled_expired_and_legacy_mix(): void
    {
        config(['app.timezone' => 'UTC']);
        $user = User::factory()->create();
        $period = now()->format('Y-m');
        $ledger = app(AiUsageLedger::class);

        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'usage_request_id' => null,
            'feature' => 'legacy.feature',
            'model' => 'claude-haiku-4-5-20251001',
            'estimated_cost_usd' => '0.500000',
            'created_at' => now(),
        ]);

        $settled = $ledger->reserve($user->id, 'kioku.classify', 'claude-haiku-4-5-20251001', AiMoney::of('0.010000'));
        $ledger->markInFlight($settled->id);
        $ledger->settle($settled->id, AiMoney::of('0.000037'), 3, 1);

        $expired = $ledger->reserve($user->id, 'yoyu.briefing', 'claude-sonnet-4-6', AiMoney::of('0.200000'));
        $ledger->markInFlight($expired->id);
        $ledger->expire($expired->id);

        $expected = '0.700037';
        $this->assertSame($expected, $ledger->sumCanonicalSpent($user->id, $period)->toString());

        Artisan::call('ai:usage-reconcile', ['--period' => $period]);
        $monthly = AiUsageMonthly::query()->withoutUserScope()->where('user_id', $user->id)->first();
        $this->assertNotNull($monthly);
        $this->assertSame($expected, AiMoney::of((string) $monthly->spent_usd)->toString());

        $summary = app(AiUsageSummary::class)->forUser($user->id, $period);
        $this->assertSame($expected, $summary['spent_usd']);
        $this->assertSame($expected, AiMoney::of((string) $monthly->spent_usd)->toString());
        $this->assertSame(1, $summary['expired_count']);

        $byFeature = collect($summary['by_feature'])->keyBy('feature');
        $this->assertTrue($byFeature->has('legacy.feature'));
        $this->assertTrue($byFeature->has('kioku.classify'));
        $this->assertTrue($byFeature->has('yoyu.briefing'));
        $this->assertSame('0.000037', $byFeature['kioku.classify']['spent_usd']);
        $this->assertSame('0.200000', $byFeature['yoyu.briefing']['spent_usd']);
        $this->assertSame('0.500000', $byFeature['legacy.feature']['spent_usd']);
    }
}
