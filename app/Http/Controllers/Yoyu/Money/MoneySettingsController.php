<?php

namespace App\Http\Controllers\Yoyu\Money;

use App\Domain\Yoyu\Money\Models\MoneyCategory;
use App\Domain\Yoyu\Money\Services\MoneySetupService;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Yoyu\Money\SetupMoneySettingsRequest;
use App\Http\Requests\Yoyu\Money\UpdateMoneySettingsRequest;
use App\Http\Resources\Yoyu\Money\MoneyAmountResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MoneySettingsController extends Controller
{
    public function index(
        Request $request,
        MoneySetupService $setupService,
        UserTimezoneResolver $timezoneResolver,
    ): Response {
        $user = $request->user();
        $settings = $setupService->ensureForUser($user);

        $categories = MoneyCategory::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MoneyCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'direction_scope' => $category->direction_scope->value,
                'flexibility_default' => $category->flexibility_default->value,
                'cost_behavior_default' => $category->cost_behavior_default?->value,
                'is_essential' => (bool) $category->is_essential,
                'is_active' => (bool) $category->is_active,
                'sort_order' => (int) $category->sort_order,
            ]);

        return Inertia::render('Yoyu/Money/Settings/Index', [
            'settings' => [
                'currency_code' => (string) $settings->currency_code,
                'minimum_living_budget' => $settings->minimum_living_budget_minor !== null
                    ? MoneyAmountResource::format((int) $settings->minimum_living_budget_minor, (string) $settings->currency_code)
                    : null,
                'safety_buffer' => $settings->safety_buffer_minor !== null
                    ? MoneyAmountResource::format((int) $settings->safety_buffer_minor, (string) $settings->currency_code)
                    : null,
                'uncertain_outflow_reserve_bps' => (int) $settings->uncertain_outflow_reserve_bps,
                'include_expected_income' => (bool) $settings->include_expected_income,
                'calculation_horizon_months' => (int) $settings->calculation_horizon_months,
                'formula_version' => (string) $settings->formula_version,
            ],
            'timezone' => $timezoneResolver->for($user),
            'categories' => $categories,
        ]);
    }

    public function update(
        UpdateMoneySettingsRequest $request,
        MoneySetupService $setupService,
    ): RedirectResponse {
        $data = MoneyAmountResource::castMinors($request->validated(), [
            'minimum_living_budget_minor',
            'safety_buffer_minor',
        ]);
        $setupService->setup($request->user(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => '設定を保存しました。']);

        return redirect()->back();
    }

    public function setup(
        SetupMoneySettingsRequest $request,
        MoneySetupService $setupService,
    ): RedirectResponse {
        $data = MoneyAmountResource::castMinors($request->validated(), [
            'minimum_living_budget_minor',
            'safety_buffer_minor',
        ]);
        $setupService->setup($request->user(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'お金の余裕の初期設定が完了しました。']);

        return redirect()->route('yoyu.money.dashboard');
    }
}
