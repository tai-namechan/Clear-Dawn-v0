<?php

namespace Tests\Feature\Ai;

use App\Domain\Shared\AI\AiMoney;
use App\Domain\Shared\AI\AiUsageLedger;
use App\Domain\Shared\Models\AiUsageLog;
use App\Domain\Shared\Models\AiUsageMonthly;
use App\Domain\Shared\Models\AiUsageRequest;
use App\Enums\AiUsageRequestStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AiUsageUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_ai_usage_settings(): void
    {
        $this->get(route('ai-usage.edit'))->assertRedirect(route('login'));
    }

    public function test_zero_usage_state(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '10']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ai-usage.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/AiUsage')
                ->where('usage.spent_usd', '0.000000')
                ->where('usage.reserved_usd', '0.000000')
                ->where('usage.limit_usd', '10.000000')
                ->where('usage.remaining_usd', '10.000000')
                ->where('usage.warning', false)
                ->where('usage.at_limit', false)
                ->where('usage.expired_count', 0)
                ->has('usage.by_model', 0)
                ->has('usage.by_feature', 0)
            );
    }

    public function test_active_usage_breakdown(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '10', 'app.timezone' => 'UTC']);
        $user = User::factory()->create();
        $other = User::factory()->create();

        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'feature' => 'kioku.classify',
            'model' => 'claude-haiku-4-5-20251001',
            'estimated_cost_usd' => '0.1000',
            'created_at' => now(),
        ]);
        AiUsageLog::factory()->create([
            'user_id' => $user->id,
            'feature' => 'yoyu.briefing',
            'model' => 'claude-sonnet-4-6',
            'estimated_cost_usd' => '0.2000',
            'created_at' => now(),
        ]);
        AiUsageLog::factory()->create([
            'user_id' => $other->id,
            'feature' => 'kioku.classify',
            'model' => 'claude-haiku-4-5-20251001',
            'estimated_cost_usd' => '9.0000',
            'created_at' => now(),
        ]);

        $ledger = app(AiUsageLedger::class);
        $period = now()->format('Y-m');
        $monthly = $ledger->ensureMonthly($user->id, $period);
        $monthly->update([
            'spent_usd' => '0.300000',
            'reserved_usd' => '0.050000',
        ]);

        $this->actingAs($user)
            ->get(route('ai-usage.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/AiUsage')
                ->where('usage.spent_usd', '0.300000')
                ->where('usage.reserved_usd', '0.050000')
                ->where('usage.remaining_usd', '9.650000')
                ->has('usage.by_model', 2)
                ->has('usage.by_feature', 2)
            );
    }

    public function test_warning_at_eighty_percent_and_limit_at_hundred(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '10']);
        $user = User::factory()->create();
        $ledger = app(AiUsageLedger::class);
        $period = now()->format('Y-m');
        $monthly = $ledger->ensureMonthly($user->id, $period);

        $monthly->update(['spent_usd' => '8.000000', 'reserved_usd' => '0.000000']);

        $this->actingAs($user)
            ->get(route('ai-usage.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage.warning', true)
                ->where('usage.at_limit', false)
                ->where('usage.progress_ratio', '0.800000')
            );

        $monthly->update(['spent_usd' => '9.000000', 'reserved_usd' => '1.000000']);

        $this->actingAs($user)
            ->get(route('ai-usage.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage.warning', true)
                ->where('usage.at_limit', true)
                ->where('usage.remaining_usd', '0.000000')
            );
    }

    public function test_reserved_amount_is_shown(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '10']);
        $user = User::factory()->create();
        app(AiUsageLedger::class)->reserve(
            $user->id,
            'kioku.classify',
            'claude-haiku-4-5-20251001',
            AiMoney::of('0.123456'),
        );

        $this->actingAs($user)
            ->get(route('ai-usage.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage.reserved_usd', '0.123456')
                ->where('usage.spent_usd', '0.000000')
            );
    }

    public function test_expired_count_is_reported(): void
    {
        $user = User::factory()->create();
        $period = now()->format('Y-m');
        app(AiUsageLedger::class)->ensureMonthly($user->id, $period);

        AiUsageRequest::factory()->create([
            'user_id' => $user->id,
            'period' => $period,
            'status' => AiUsageRequestStatus::Expired,
            'charged_usd' => '0.010000',
            'finished_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('ai-usage.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage.expired_count', 1)
            );
    }

    public function test_other_user_cannot_see_foreign_usage_via_page(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '10']);
        $user = User::factory()->create();
        $other = User::factory()->create();

        AiUsageMonthly::factory()->create([
            'user_id' => $other->id,
            'spent_usd' => '7.000000',
            'reserved_usd' => '1.000000',
        ]);

        $this->actingAs($user)
            ->get(route('ai-usage.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage.spent_usd', '0.000000')
                ->where('usage.reserved_usd', '0.000000')
            );
    }
}
