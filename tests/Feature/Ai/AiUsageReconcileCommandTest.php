<?php

namespace Tests\Feature\Ai;

use App\Domain\Shared\AI\AiMoney;
use App\Domain\Shared\AI\AiUsageLedger;
use App\Domain\Shared\AI\AiUsageReconciler;
use App\Domain\Shared\Models\AiUsageLog;
use App\Domain\Shared\Models\AiUsageRequest;
use App\Enums\AiUsageRequestStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AiUsageReconcileCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_command_is_idempotent(): void
    {
        config(['app.timezone' => 'UTC']);
        $user = User::factory()->create();
        $period = now()->format('Y-m');

        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'estimated_cost_usd' => '1.5000',
            'created_at' => now(),
        ]);

        $ledger = app(AiUsageLedger::class);
        $monthly = $ledger->ensureMonthly($user->id, $period);
        $monthly->update(['spent_usd' => '0.000000']);

        $this->artisan('ai:usage-reconcile', ['--period' => $period])->assertSuccessful();
        $this->assertSame('1.500000', AiMoney::of((string) $monthly->fresh()->spent_usd)->toString());

        $this->artisan('ai:usage-reconcile', ['--period' => $period])->assertSuccessful();
        $this->assertSame('1.500000', AiMoney::of((string) $monthly->fresh()->spent_usd)->toString());

        $result = app(AiUsageReconciler::class)->reconcilePeriod($period);
        $this->assertSame(0, $result['adjusted']);
    }

    public function test_reconcile_includes_expired_charged_amounts(): void
    {
        config(['app.timezone' => 'UTC']);
        $user = User::factory()->create();
        $period = now()->format('Y-m');

        $ledger = app(AiUsageLedger::class);
        $monthly = $ledger->ensureMonthly($user->id, $period);

        AiUsageRequest::factory()->create([
            'user_id' => $user->id,
            'period' => $period,
            'estimated_usd' => '0.300000',
            'charged_usd' => '0.300000',
            'status' => AiUsageRequestStatus::Expired,
            'finished_at' => now(),
        ]);

        Artisan::call('ai:usage-reconcile', ['--period' => $period]);

        $this->assertSame('0.300000', AiMoney::of((string) $monthly->fresh()->spent_usd)->toString());
    }
}
