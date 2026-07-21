<?php

namespace Tests\Feature\Yoyu\Money;

use App\Domain\Yoyu\Money\Enums\MoneyAccountType;
use App\Domain\Yoyu\Money\Enums\MoneyCashflowKind;
use App\Domain\Yoyu\Money\Enums\MoneyCertainty;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyFlexibility;
use App\Domain\Yoyu\Money\Enums\MoneyLoanStatus;
use App\Domain\Yoyu\Money\Enums\MoneyLoanType;
use App\Domain\Yoyu\Money\Enums\MoneyPriority;
use App\Domain\Yoyu\Money\Enums\MoneySimulationStatus;
use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Models\MoneyCreditCard;
use App\Domain\Yoyu\Money\Models\MoneyLoan;
use App\Domain\Yoyu\Money\Models\MoneySimulation;
use App\Domain\Yoyu\Money\Services\MoneySetupService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MoneyUiRebuildTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exposes_setup_progress_timeline_and_debt_summary(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('yoyu.money.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Yoyu/Money/Dashboard', false)
                ->has('setup_progress')
                ->where('setup_progress.required_complete', false)
                ->has('setup_progress.steps', 5)
                ->has('balance_timeline')
                ->has('month_summary')
                ->has('debt_summary')
                ->has('adjustment_candidates')
                ->has('upcoming_payments')
                ->where('debt_summary.credit_available_minor', null)
            );
    }

    public function test_setup_progress_completes_from_existing_data_without_flags(): void
    {
        $user = User::factory()->create();
        $setup = app(MoneySetupService::class);
        $settings = $setup->ensureForUser($user);
        $settings->forceFill([
            'minimum_living_budget_minor' => 100000,
            'safety_buffer_minor' => 50000,
        ])->save();

        MoneyAccount::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'name' => 'メイン口座',
            'type' => MoneyAccountType::Bank,
            'currency_code' => 'JPY',
            'current_balance_minor' => 120000,
            'balance_as_of' => now(),
            'is_active' => true,
            'lock_version' => 1,
        ]);

        MoneyCashflow::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'direction' => MoneyDirection::Inflow,
            'kind' => MoneyCashflowKind::Income,
            'name' => '給与',
            'amount_minor' => 270000,
            'currency_code' => 'JPY',
            'due_on' => now()->addDays(3)->toDateString(),
            'status' => 'planned',
            'certainty' => MoneyCertainty::Confirmed,
            'flexibility' => MoneyFlexibility::Required,
            'priority' => MoneyPriority::High,
            'lock_version' => 1,
        ]);

        MoneyCashflow::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'direction' => MoneyDirection::Outflow,
            'kind' => MoneyCashflowKind::Expense,
            'name' => '家賃',
            'amount_minor' => 70000,
            'currency_code' => 'JPY',
            'due_on' => now()->addDays(5)->toDateString(),
            'status' => 'planned',
            'certainty' => MoneyCertainty::Confirmed,
            'flexibility' => MoneyFlexibility::Required,
            'priority' => MoneyPriority::High,
            'lock_version' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.money.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('setup_progress.required_complete', true)
                ->where('setup_progress.is_complete', true)
                ->where('margin.is_complete', true)
                ->where('funds_minor', '120000')
            );
    }

    public function test_card_available_credit_is_not_added_to_funds(): void
    {
        $user = User::factory()->create();

        MoneyAccount::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'name' => '銀行',
            'type' => MoneyAccountType::Bank,
            'currency_code' => 'JPY',
            'current_balance_minor' => 50000,
            'balance_as_of' => now(),
            'is_active' => true,
            'lock_version' => 1,
        ]);

        MoneyCreditCard::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'name' => 'メインカード',
            'currency_code' => 'JPY',
            'closing_day' => '15',
            'payment_day' => '10',
            'limit_minor' => 500000,
            'available_minor' => 400000,
            'current_statement_minor' => 20000,
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.money.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('funds_minor', '50000')
                ->where('debt_summary.credit_available_minor', '400000')
                ->where('debt_summary.card_statement_minor', '20000')
                ->where('credit_facility_note', 'card_available_excluded_from_funds')
            );
    }

    public function test_balance_timeline_includes_running_balance_after_each_event(): void
    {
        $user = User::factory()->create();

        MoneyAccount::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'name' => '銀行',
            'type' => MoneyAccountType::Bank,
            'currency_code' => 'JPY',
            'current_balance_minor' => 100000,
            'balance_as_of' => now(),
            'is_active' => true,
            'lock_version' => 1,
        ]);

        MoneyCashflow::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'direction' => MoneyDirection::Outflow,
            'kind' => MoneyCashflowKind::Expense,
            'name' => 'カード請求',
            'amount_minor' => 30000,
            'currency_code' => 'JPY',
            'due_on' => now()->addDays(2)->toDateString(),
            'status' => 'planned',
            'certainty' => MoneyCertainty::Confirmed,
            'flexibility' => MoneyFlexibility::Required,
            'priority' => MoneyPriority::High,
            'lock_version' => 1,
        ]);

        MoneyCashflow::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'direction' => MoneyDirection::Inflow,
            'kind' => MoneyCashflowKind::Income,
            'name' => '給与',
            'amount_minor' => 200000,
            'currency_code' => 'JPY',
            'due_on' => now()->addDays(4)->toDateString(),
            'status' => 'planned',
            'certainty' => MoneyCertainty::Confirmed,
            'flexibility' => MoneyFlexibility::Required,
            'priority' => MoneyPriority::High,
            'lock_version' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.money.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('balance_timeline', 2)
                ->where('balance_timeline.0.name', 'カード請求')
                ->where('balance_timeline.0.balance_after_minor', '70000')
                ->where('balance_timeline.1.name', '給与')
                ->where('balance_timeline.1.balance_after_minor', '270000')
            );
    }

    public function test_stoppable_cashflow_appears_as_adjustment_candidate(): void
    {
        $user = User::factory()->create();

        MoneyCashflow::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'direction' => MoneyDirection::Outflow,
            'kind' => MoneyCashflowKind::Expense,
            'name' => 'ジム',
            'amount_minor' => 7500,
            'currency_code' => 'JPY',
            'due_on' => now()->addDays(8)->toDateString(),
            'status' => 'planned',
            'certainty' => MoneyCertainty::Confirmed,
            'flexibility' => MoneyFlexibility::Stoppable,
            'priority' => MoneyPriority::Low,
            'lock_version' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.money.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('adjustment_candidates', 1)
                ->where('adjustment_candidates.0.type', 'stoppable')
                ->where('adjustment_candidates.0.amount_minor', '7500')
            );
    }

    public function test_legacy_money_urls_still_render_expected_pages(): void
    {
        $user = User::factory()->create();

        $cases = [
            ['yoyu.money.accounts.index', 'Yoyu/Money/Accounts/Index'],
            ['yoyu.money.cards.index', 'Yoyu/Money/Cards/Index'],
            ['yoyu.money.loans.index', 'Yoyu/Money/Loans/Index'],
            ['yoyu.money.transactions.index', 'Yoyu/Money/Transactions/Index'],
            ['yoyu.money.imports.index', 'Yoyu/Money/Imports/Index'],
            ['yoyu.money.analysis.index', 'Yoyu/Money/Analysis/Index'],
            ['yoyu.money.simulations.index', 'Yoyu/Money/Simulations/Index'],
            ['yoyu.money.decisions.index', 'Yoyu/Money/Decisions/Index'],
            ['yoyu.money.settings.index', 'Yoyu/Money/Settings/Index'],
            ['yoyu.money.cashflows.index', 'Yoyu/Money/Cashflows/Index'],
        ];

        foreach ($cases as [$route, $component]) {
            $this->actingAs($user)
                ->get(route($route))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component($component, false));
        }
    }

    public function test_cashflows_month_page_includes_running_balance_fields(): void
    {
        $user = User::factory()->create();

        MoneyAccount::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'name' => '銀行',
            'type' => MoneyAccountType::Bank,
            'currency_code' => 'JPY',
            'current_balance_minor' => 80000,
            'balance_as_of' => now(),
            'is_active' => true,
            'lock_version' => 1,
        ]);

        MoneyCashflow::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'direction' => MoneyDirection::Outflow,
            'kind' => MoneyCashflowKind::Expense,
            'name' => '家賃',
            'amount_minor' => 70000,
            'currency_code' => 'JPY',
            'due_on' => now()->toDateString(),
            'status' => 'planned',
            'certainty' => MoneyCertainty::Confirmed,
            'flexibility' => MoneyFlexibility::Required,
            'priority' => MoneyPriority::High,
            'lock_version' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.money.cashflows.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Yoyu/Money/Cashflows/Index', false)
                ->has('cashflows', 1)
                ->has('cashflows.0.balance_after_minor')
                ->has('cashflows.0.flexibility')
                ->has('month')
            );
    }

    public function test_unset_living_budget_is_null_not_zero_string(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('yoyu.money.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.minimum_living_budget_minor', null)
                ->where('settings.safety_buffer_minor', null)
                ->where('margin.is_complete', false)
            );
    }

    public function test_saving_simulation_does_not_mutate_cashflows(): void
    {
        $user = User::factory()->create();

        MoneyAccount::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'name' => '銀行',
            'type' => MoneyAccountType::Bank,
            'currency_code' => 'JPY',
            'current_balance_minor' => 100000,
            'balance_as_of' => now(),
            'is_active' => true,
            'lock_version' => 1,
        ]);

        /** @var MoneyCashflow $cashflow */
        $cashflow = MoneyCashflow::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'direction' => MoneyDirection::Outflow,
            'kind' => MoneyCashflowKind::Expense,
            'name' => '家賃',
            'amount_minor' => 70000,
            'currency_code' => 'JPY',
            'due_on' => now()->addDays(5)->toDateString(),
            'status' => 'planned',
            'certainty' => MoneyCertainty::Confirmed,
            'flexibility' => MoneyFlexibility::Adjustable,
            'priority' => MoneyPriority::High,
            'lock_version' => 1,
        ]);

        $countBefore = MoneyCashflow::query()->withoutUserScope()->where('user_id', $user->id)->count();

        $this->actingAs($user)
            ->post(route('yoyu.money.simulations.store'), [
                'name' => '延期案',
                'horizon_months' => 3,
            ])
            ->assertRedirect();

        $simulation = MoneySimulation::query()->withoutUserScope()->where('user_id', $user->id)->first();
        $this->assertNotNull($simulation);
        $this->assertSame(MoneySimulationStatus::Draft, $simulation->status);

        $countAfter = MoneyCashflow::query()->withoutUserScope()->where('user_id', $user->id)->count();
        $this->assertSame($countBefore, $countAfter);

        $cashflow->refresh();
        $this->assertSame('planned', $cashflow->status->value);
        $this->assertSame(70000, (int) $cashflow->amount_minor);
    }

    public function test_user_cannot_see_another_users_money_props(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        MoneyAccount::query()->withoutUserScope()->create([
            'user_id' => $owner->id,
            'name' => '所有者の口座',
            'type' => MoneyAccountType::Bank,
            'currency_code' => 'JPY',
            'current_balance_minor' => 999999,
            'balance_as_of' => now(),
            'is_active' => true,
            'lock_version' => 1,
        ]);

        $this->actingAs($other)
            ->get(route('yoyu.money.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('funds_minor', '0')
                ->has('accounts', 0)
            );
    }

    public function test_loan_candidate_appears_without_counting_as_funds(): void
    {
        $user = User::factory()->create();

        MoneyLoan::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'name' => '教育ローン',
            'type' => MoneyLoanType::Other,
            'currency_code' => 'JPY',
            'outstanding_principal_minor' => 500000,
            'monthly_payment_minor' => 20000,
            'balance_as_of' => now(),
            'next_payment_on' => now()->addDays(10)->toDateString(),
            'status' => MoneyLoanStatus::Active,
            'lock_version' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('yoyu.money.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('funds_minor', '0')
                ->where('debt_summary.outstanding_debt_minor', '500000')
                ->where('debt_summary.monthly_repayment_minor', '20000')
                ->has('adjustment_candidates', 1)
                ->where('adjustment_candidates.0.type', 'loan_prepay')
            );
    }
}
