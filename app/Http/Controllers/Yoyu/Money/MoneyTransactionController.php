<?php

namespace App\Http\Controllers\Yoyu\Money;

use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyTransaction;
use App\Domain\Yoyu\Money\Services\MoneyTransactionService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Yoyu\Money\Concerns\EnsuresMoneyOwnership;
use App\Http\Requests\Yoyu\Money\StoreMoneyTransactionRequest;
use App\Http\Requests\Yoyu\Money\VoidMoneyTransactionRequest;
use App\Http\Resources\Yoyu\Money\MoneyAmountResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MoneyTransactionController extends Controller
{
    use EnsuresMoneyOwnership;

    public function index(Request $request, MoneyTransactionService $transactionService): Response
    {
        $paginator = $transactionService->paginateForUser($request->user(), 50);

        $transactions = collect($paginator->items())
            ->map(fn (MoneyTransaction $transaction): array => [
                'id' => $transaction->id,
                'account_id' => $transaction->account_id,
                'direction' => $transaction->direction->value,
                'kind' => $transaction->kind->value,
                'status' => $transaction->status->value,
                'occurred_on' => (string) $transaction->occurred_on?->toDateString(),
                'description' => $transaction->description_normalized ?? $transaction->description_raw,
                'amount' => MoneyAmountResource::format(
                    (int) $transaction->amount_minor,
                    (string) $transaction->currency_code,
                ),
                'voided_at' => $transaction->voided_at?->toIso8601String(),
            ]);

        $accounts = MoneyAccount::query()
            ->withoutUserScope()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (MoneyAccount $account): array => [
                'id' => $account->id,
                'name' => $account->name,
            ]);

        return Inertia::render('Yoyu/Money/Transactions/Index', [
            'transactions' => $transactions,
            'accounts' => $accounts,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(
        StoreMoneyTransactionRequest $request,
        MoneyTransactionService $transactionService,
    ): RedirectResponse {
        $data = MoneyAmountResource::castMinors($request->validated(), ['amount_minor']);
        $transactionService->createManual($request->user(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => '取引を登録しました。']);

        return redirect()->back();
    }

    public function void(
        VoidMoneyTransactionRequest $request,
        MoneyTransaction $transaction,
        MoneyTransactionService $transactionService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $transaction);

        $reason = $request->validated('reason');
        $transactionService->void(
            $request->user(),
            $transaction,
            is_string($reason) ? $reason : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => '取引を取り消しました。']);

        return redirect()->back();
    }
}
