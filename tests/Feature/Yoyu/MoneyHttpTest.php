<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Yoyu\Money\Enums\MoneyAccountType;
use App\Domain\Yoyu\Money\Enums\MoneyCashflowKind;
use App\Domain\Yoyu\Money\Enums\MoneyCertainty;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyFlexibility;
use App\Domain\Yoyu\Money\Enums\MoneyPriority;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MoneyHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_money_dashboard(): void
    {
        $this->get(route('yoyu.money.dashboard'))->assertRedirect(route('login'));
    }

    public function test_finance_redirects_to_money_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/finance')
            ->assertRedirect('/yoyu/money');
    }

    public function test_authenticated_user_can_open_money_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('yoyu.money.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Yoyu/Money/Dashboard', false)
                ->has('funds_minor')
                ->has('margin')
                ->has('settings')
                ->has('accounts')
            );
    }

    public function test_user_can_create_money_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('yoyu.money.accounts.store'), [
                'name' => 'メイン口座',
                'type' => MoneyAccountType::Bank->value,
                'currency_code' => 'JPY',
                'current_balance_minor' => '100000',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('yoyu_money_accounts', [
            'user_id' => $user->id,
            'name' => 'メイン口座',
            'current_balance_minor' => 100000,
        ]);
    }

    public function test_user_cannot_settle_another_users_cashflow(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        /** @var MoneyCashflow $cashflow */
        $cashflow = MoneyCashflow::query()->withoutUserScope()->create([
            'user_id' => $owner->id,
            'direction' => MoneyDirection::Outflow,
            'kind' => MoneyCashflowKind::Expense,
            'name' => '家賃',
            'amount_minor' => 80000,
            'currency_code' => 'JPY',
            'due_on' => now()->toDateString(),
            'status' => 'planned',
            'certainty' => MoneyCertainty::Confirmed,
            'flexibility' => MoneyFlexibility::Required,
            'priority' => MoneyPriority::High,
            'lock_version' => 1,
        ]);

        $this->actingAs($other)
            ->post(route('yoyu.money.cashflows.settle', $cashflow), [
                'amount_minor' => '80000',
                'occurred_on' => now()->toDateString(),
            ])
            ->assertNotFound();
    }

    public function test_settle_with_stale_lock_version_returns_409(): void
    {
        $user = User::factory()->create();

        /** @var MoneyCashflow $cashflow */
        $cashflow = MoneyCashflow::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'direction' => MoneyDirection::Outflow,
            'kind' => MoneyCashflowKind::Expense,
            'name' => '家賃',
            'amount_minor' => 80000,
            'currency_code' => 'JPY',
            'due_on' => now()->toDateString(),
            'status' => 'planned',
            'certainty' => MoneyCertainty::Confirmed,
            'flexibility' => MoneyFlexibility::Required,
            'priority' => MoneyPriority::High,
            'lock_version' => 2,
        ]);

        $this->actingAs($user)
            ->post(route('yoyu.money.cashflows.settle', $cashflow), [
                'amount_minor' => '80000',
                'occurred_on' => now()->toDateString(),
                'lock_version' => 1,
            ])
            ->assertStatus(409);

        $this->assertSame('planned', $cashflow->refresh()->status->value);

        $this->actingAs($user)
            ->post(route('yoyu.money.cashflows.settle', $cashflow), [
                'amount_minor' => '80000',
                'occurred_on' => now()->toDateString(),
                'lock_version' => 2,
            ])
            ->assertRedirect();

        $this->assertSame('settled', $cashflow->refresh()->status->value);
    }

    public function test_settings_index_includes_categories_after_setup(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('yoyu.money.settings.setup'), [
                'minimum_living_budget_minor' => '150000',
                'safety_buffer_minor' => '50000',
                'include_expected_income' => false,
                'calculation_horizon_months' => 3,
            ])
            ->assertRedirect(route('yoyu.money.dashboard'));

        $this->actingAs($user)
            ->get(route('yoyu.money.settings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Yoyu/Money/Settings/Index', false)
                ->has('settings')
                ->has('categories')
                ->has('timezone')
            );
    }
}
