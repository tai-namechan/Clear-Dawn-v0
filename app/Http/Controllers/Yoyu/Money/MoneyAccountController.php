<?php

namespace App\Http\Controllers\Yoyu\Money;

use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Services\MoneyAccountService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Yoyu\Money\Concerns\EnsuresMoneyOwnership;
use App\Http\Requests\Yoyu\Money\StoreMoneyAccountRequest;
use App\Http\Requests\Yoyu\Money\ToggleMoneyAccountRequest;
use App\Http\Requests\Yoyu\Money\UpdateMoneyAccountBalanceRequest;
use App\Http\Resources\Yoyu\Money\MoneyAmountResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MoneyAccountController extends Controller
{
    use EnsuresMoneyOwnership;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $accounts = MoneyAccount::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get()
            ->map(fn (MoneyAccount $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
                'currency_code' => (string) $account->currency_code,
                'current_balance' => MoneyAmountResource::format(
                    (int) $account->current_balance_minor,
                    (string) $account->currency_code,
                ),
                'available_balance' => $account->available_balance_minor !== null
                    ? MoneyAmountResource::format(
                        (int) $account->available_balance_minor,
                        (string) $account->currency_code,
                    )
                    : null,
                'balance_as_of' => $account->balance_as_of?->toIso8601String(),
                'is_active' => (bool) $account->is_active,
                'lock_version' => (int) $account->lock_version,
            ]);

        return Inertia::render('Yoyu/Money/Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function store(
        StoreMoneyAccountRequest $request,
        MoneyAccountService $accountService,
    ): RedirectResponse {
        $data = MoneyAmountResource::castMinors(
            $request->validated(),
            [],
            ['current_balance_minor', 'available_balance_minor'],
        );
        $accountService->create($request->user(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => '口座を追加しました。']);

        return redirect()->back();
    }

    public function updateBalance(
        UpdateMoneyAccountBalanceRequest $request,
        MoneyAccount $account,
        MoneyAccountService $accountService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $account);

        $data = MoneyAmountResource::castMinors(
            $request->validated(),
            [],
            ['current_balance_minor', 'available_balance_minor'],
        );

        $accountService->updateBalance(
            $request->user(),
            $account,
            (int) $data['current_balance_minor'],
            array_key_exists('available_balance_minor', $data)
                ? ($data['available_balance_minor'] !== null ? (int) $data['available_balance_minor'] : null)
                : null,
            isset($data['note']) ? (string) $data['note'] : null,
            (int) $data['lock_version'],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => '残高を更新しました。']);

        return redirect()->back();
    }

    public function toggle(
        ToggleMoneyAccountRequest $request,
        MoneyAccount $account,
        MoneyAccountService $accountService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $account);

        $accountService->toggleActive(
            $request->user(),
            $account,
            $request->boolean('is_active'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => '口座の状態を更新しました。']);

        return redirect()->back();
    }
}
