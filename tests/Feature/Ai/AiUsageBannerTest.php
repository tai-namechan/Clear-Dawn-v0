<?php

namespace Tests\Feature\Ai;

use App\Domain\Shared\AI\AiUsageLedger;
use App\Domain\Shared\Models\AiUsageMonthly;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AiUsageBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_banner_hidden_below_eighty_percent(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '10']);
        $user = User::factory()->create();
        $monthly = app(AiUsageLedger::class)->ensureMonthly($user->id, now()->format('Y-m'));
        $monthly->update([
            'spent_usd' => '7.999990',
            'reserved_usd' => '0.000000',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('aiUsageBanner', null)
            );
    }

    public function test_banner_shows_at_exactly_eighty_percent(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '10']);
        $user = User::factory()->create();
        $monthly = app(AiUsageLedger::class)->ensureMonthly($user->id, now()->format('Y-m'));
        $monthly->update([
            'spent_usd' => '8.000000',
            'reserved_usd' => '0.000000',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('aiUsageBanner.warning', true)
                ->where('aiUsageBanner.at_limit', false)
                ->where('aiUsageBanner.progress_ratio', '0.800000')
            );
    }

    public function test_banner_includes_reserved_in_threshold(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '10']);
        $user = User::factory()->create();
        $monthly = app(AiUsageLedger::class)->ensureMonthly($user->id, now()->format('Y-m'));
        $monthly->update([
            'spent_usd' => '7.000000',
            'reserved_usd' => '1.000000',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('aiUsageBanner.warning', true)
                ->where('aiUsageBanner.reserved_usd', '1.000000')
            );
    }

    public function test_banner_at_limit(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '10']);
        $user = User::factory()->create();
        $monthly = app(AiUsageLedger::class)->ensureMonthly($user->id, now()->format('Y-m'));
        $monthly->update([
            'spent_usd' => '9.500000',
            'reserved_usd' => '0.500000',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('aiUsageBanner.at_limit', true)
                ->where('aiUsageBanner.remaining_usd', '0.000000')
            );
    }

    public function test_banner_is_user_scoped(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '10']);
        $user = User::factory()->create();
        $other = User::factory()->create();

        AiUsageMonthly::factory()->create([
            'user_id' => $other->id,
            'spent_usd' => '10.000000',
            'reserved_usd' => '0.000000',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('aiUsageBanner', null)
            );
    }
}
