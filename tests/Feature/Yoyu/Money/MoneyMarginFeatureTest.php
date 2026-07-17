<?php

namespace Tests\Feature\Yoyu\Money;

use App\Domain\Yoyu\Money\Enums\MoneyAccountType;
use App\Domain\Yoyu\Money\Enums\MoneyCashflowKind;
use App\Domain\Yoyu\Money\Enums\MoneyCashflowStatus;
use App\Domain\Yoyu\Money\Enums\MoneyCertainty;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyFlexibility;
use App\Domain\Yoyu\Money\Enums\MoneyPriority;
use App\Domain\Yoyu\Money\Enums\MoneyRecurringFrequency;
use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Models\MoneyRecurringRule;
use App\Domain\Yoyu\Money\Services\MoneySetupService;
use App\Domain\Yoyu\Money\Services\RecurringCashflowGenerator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MoneyMarginFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_margin_matches_ac01_fixture(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);

        app(MoneySetupService::class)->setup($user, [
            'timezone' => 'Asia/Tokyo',
            'minimum_living_budget_minor' => 20_000,
            'safety_buffer_minor' => 10_000,
        ]);

        MoneyAccount::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'name' => '現金',
            'type' => MoneyAccountType::Cash,
            'currency_code' => 'JPY',
            'current_balance_minor' => 100_000,
            'available_balance_minor' => 100_000,
            'balance_as_of' => now(),
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $this->createCashflow($user->id, MoneyDirection::Inflow, MoneyCashflowKind::Income, 50_000, now()->addDays(5)->toDateString());
        $this->createCashflow($user->id, MoneyDirection::Outflow, MoneyCashflowKind::Expense, 80_000, now()->addDays(10)->toDateString());

        $this->actingAs($user)
            ->get(route('yoyu.money.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Yoyu/Money/Dashboard')
                ->where('funds_minor', '100000')
                ->where('margin.confirmed_income_minor', '50000')
                ->where('margin.confirmed_outflow_minor', '80000')
                ->where('margin.projected_cash_minor', '70000')
                ->where('margin.projected_margin_minor', '40000')
                ->where('margin.safe_to_spend_minor', '40000')
                ->where('margin.shortfall_minor', '0')
                ->where('margin.is_complete', true)
            );
    }

    public function test_dashboard_shows_shortfall_for_ac02(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);

        app(MoneySetupService::class)->setup($user, [
            'minimum_living_budget_minor' => 20_000,
            'safety_buffer_minor' => 10_000,
        ]);

        MoneyAccount::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'name' => '現金',
            'type' => MoneyAccountType::Cash,
            'currency_code' => 'JPY',
            'current_balance_minor' => 100_000,
            'balance_as_of' => now(),
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $this->createCashflow($user->id, MoneyDirection::Inflow, MoneyCashflowKind::Income, 50_000, now()->addDays(5)->toDateString());
        $this->createCashflow($user->id, MoneyDirection::Outflow, MoneyCashflowKind::Expense, 130_000, now()->addDays(10)->toDateString());

        $this->actingAs($user)
            ->get(route('yoyu.money.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('margin.projected_margin_minor', '-10000')
                ->where('margin.safe_to_spend_minor', '0')
                ->where('margin.shortfall_minor', '10000')
            );
    }

    public function test_incomplete_settings_are_not_presented_as_complete(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('yoyu.money.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('margin.is_complete', false)
                ->has('margin.missing_settings')
            );
    }

    public function test_recurring_month_end_rule_is_idempotent_for_february(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Tokyo']);
        app(MoneySetupService::class)->ensureForUser($user);

        MoneyRecurringRule::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'name' => '月末支払',
            'direction' => MoneyDirection::Outflow,
            'cashflow_kind' => MoneyCashflowKind::Expense,
            'amount_minor' => 10_000,
            'currency_code' => 'JPY',
            'frequency' => MoneyRecurringFrequency::Monthly,
            'interval_count' => 1,
            'day_of_month' => 31,
            'start_on' => '2026-01-31',
            'timezone' => 'Asia/Tokyo',
            'certainty' => MoneyCertainty::Confirmed,
            'flexibility' => MoneyFlexibility::Required,
            'priority' => MoneyPriority::Normal,
            'is_active' => true,
        ]);

        $generator = app(RecurringCashflowGenerator::class);
        $first = $generator->generateForUser($user, CarbonImmutable::parse('2026-03-31', 'Asia/Tokyo'));
        $second = $generator->generateForUser($user, CarbonImmutable::parse('2026-03-31', 'Asia/Tokyo'));

        $this->assertGreaterThan(0, $first);
        $this->assertSame(0, $second);

        $february = MoneyCashflow::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->whereDate('occurrence_on', '2026-02-28')
            ->count();

        $this->assertSame(1, $february);
    }

    public function test_other_users_account_is_not_listed(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        MoneyAccount::query()->withoutUserScope()->create([
            'user_id' => $owner->id,
            'name' => '他人の口座',
            'type' => MoneyAccountType::Bank,
            'currency_code' => 'JPY',
            'current_balance_minor' => 999_999,
            'balance_as_of' => now(),
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $this->actingAs($other)
            ->get(route('yoyu.money.accounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('accounts', [])
            );
    }

    private function createCashflow(
        int $userId,
        MoneyDirection $direction,
        MoneyCashflowKind $kind,
        int $amountMinor,
        string $dueOn,
    ): void {
        MoneyCashflow::query()->withoutUserScope()->create([
            'user_id' => $userId,
            'direction' => $direction,
            'kind' => $kind,
            'name' => 'fixture',
            'amount_minor' => $amountMinor,
            'currency_code' => 'JPY',
            'due_on' => $dueOn,
            'status' => MoneyCashflowStatus::Confirmed,
            'certainty' => MoneyCertainty::Confirmed,
            'flexibility' => MoneyFlexibility::Adjustable,
            'priority' => MoneyPriority::Normal,
            'lock_version' => 1,
        ]);
    }
}
