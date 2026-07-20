<?php

namespace Tests\Feature\Yoyu\Money;

use App\Domain\Yoyu\Money\Enums\MoneyAccountType;
use App\Domain\Yoyu\Money\Enums\MoneyCashflowKind;
use App\Domain\Yoyu\Money\Enums\MoneyCertainty;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyFlexibility;
use App\Domain\Yoyu\Money\Enums\MoneyPriority;
use App\Domain\Yoyu\Money\Enums\MoneySimulationStatus;
use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Models\MoneySimulation;
use App\Domain\Yoyu\Money\Services\MoneySetupService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoneySimulationStaleTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithMoneyData(): User
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

        return $user;
    }

    private function createCashflow(User $user, int $amountMinor): MoneyCashflow
    {
        return MoneyCashflow::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'direction' => MoneyDirection::Outflow,
            'kind' => MoneyCashflowKind::Expense,
            'name' => '家賃',
            'amount_minor' => $amountMinor,
            'currency_code' => 'JPY',
            'due_on' => now()->addDays(10)->toDateString(),
            'status' => 'planned',
            'certainty' => MoneyCertainty::Confirmed,
            'flexibility' => MoneyFlexibility::Required,
            'priority' => MoneyPriority::High,
            'lock_version' => 1,
        ]);
    }

    public function test_apply_is_rejected_with_409_when_baseline_data_changed(): void
    {
        $user = $this->createUserWithMoneyData();
        $this->createCashflow($user, 80_000);

        $this->actingAs($user)
            ->post(route('yoyu.money.simulations.store'), ['name' => 'テスト'])
            ->assertRedirect();

        /** @var MoneySimulation $simulation */
        $simulation = MoneySimulation::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('yoyu.money.simulations.calculate', $simulation))
            ->assertRedirect();

        $this->assertSame(MoneySimulationStatus::Calculated, $simulation->refresh()->status);

        // 実データを変更して fingerprint を変える
        $this->createCashflow($user, 5_000);

        $this->actingAs($user)
            ->post(route('yoyu.money.simulations.apply', $simulation))
            ->assertStatus(409);

        $this->assertSame(MoneySimulationStatus::Stale, $simulation->refresh()->status);
    }

    public function test_apply_succeeds_when_baseline_unchanged(): void
    {
        $user = $this->createUserWithMoneyData();
        $this->createCashflow($user, 80_000);

        $this->actingAs($user)
            ->post(route('yoyu.money.simulations.store'), ['name' => 'テスト'])
            ->assertRedirect();

        /** @var MoneySimulation $simulation */
        $simulation = MoneySimulation::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('yoyu.money.simulations.calculate', $simulation))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('yoyu.money.simulations.apply', $simulation))
            ->assertRedirect();

        $this->assertSame(MoneySimulationStatus::Applied, $simulation->refresh()->status);
    }
}
