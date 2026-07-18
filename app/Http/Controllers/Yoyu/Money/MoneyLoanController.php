<?php

namespace App\Http\Controllers\Yoyu\Money;

use App\Domain\Yoyu\Money\Models\MoneyLoan;
use App\Domain\Yoyu\Money\Services\MoneyLoanService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Yoyu\Money\Concerns\EnsuresMoneyOwnership;
use App\Http\Requests\Yoyu\Money\StoreMoneyLoanPaymentRequest;
use App\Http\Requests\Yoyu\Money\StoreMoneyLoanRequest;
use App\Http\Requests\Yoyu\Money\UpdateMoneyLoanBalanceRequest;
use App\Http\Resources\Yoyu\Money\MoneyAmountResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MoneyLoanController extends Controller
{
    use EnsuresMoneyOwnership;

    public function index(Request $request, MoneyLoanService $loanService): Response
    {
        $loans = $loanService->listForUser($request->user())
            ->map(fn (MoneyLoan $loan): array => [
                'id' => $loan->id,
                'name' => $loan->name,
                'type' => $loan->type->value,
                'status' => $loan->status->value,
                'currency_code' => (string) $loan->currency_code,
                'outstanding_principal' => MoneyAmountResource::format(
                    (int) $loan->outstanding_principal_minor,
                    (string) $loan->currency_code,
                ),
                'monthly_payment' => MoneyAmountResource::format(
                    (int) $loan->monthly_payment_minor,
                    (string) $loan->currency_code,
                ),
                'next_payment_on' => (string) $loan->next_payment_on?->toDateString(),
                'lock_version' => (int) $loan->lock_version,
            ]);

        return Inertia::render('Yoyu/Money/Loans/Index', [
            'loans' => $loans,
        ]);
    }

    public function store(
        StoreMoneyLoanRequest $request,
        MoneyLoanService $loanService,
    ): RedirectResponse {
        $data = MoneyAmountResource::castMinors($request->validated(), [
            'original_principal_minor',
            'outstanding_principal_minor',
            'monthly_payment_minor',
            'minimum_payment_minor',
        ]);
        $loanService->create($request->user(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ローンを追加しました。']);

        return redirect()->back();
    }

    public function updateBalance(
        UpdateMoneyLoanBalanceRequest $request,
        MoneyLoan $loan,
        MoneyLoanService $loanService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $loan);

        $data = MoneyAmountResource::castMinors($request->validated(), ['outstanding_principal_minor']);
        $loanService->updateBalance(
            $request->user(),
            $loan,
            (int) $data['outstanding_principal_minor'],
            (int) $data['lock_version'],
            isset($data['note']) ? (string) $data['note'] : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ローン残高を更新しました。']);

        return redirect()->back();
    }

    public function storePayment(
        StoreMoneyLoanPaymentRequest $request,
        MoneyLoan $loan,
        MoneyLoanService $loanService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $loan);

        $data = MoneyAmountResource::castMinors($request->validated(), [
            'total_minor',
            'principal_minor',
            'interest_minor',
            'fee_minor',
        ]);
        $loanService->recordPayment($request->user(), $loan, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ローン返済を記録しました。']);

        return redirect()->back();
    }
}
