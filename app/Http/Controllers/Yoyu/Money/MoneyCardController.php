<?php

namespace App\Http\Controllers\Yoyu\Money;

use App\Domain\Yoyu\Money\Models\MoneyCreditCard;
use App\Domain\Yoyu\Money\Services\MoneyCardService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Yoyu\Money\Concerns\EnsuresMoneyOwnership;
use App\Http\Requests\Yoyu\Money\StoreMoneyCardRequest;
use App\Http\Requests\Yoyu\Money\StoreMoneyCardStatementRequest;
use App\Http\Requests\Yoyu\Money\UpdateMoneyCardSnapshotRequest;
use App\Http\Resources\Yoyu\Money\MoneyAmountResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MoneyCardController extends Controller
{
    use EnsuresMoneyOwnership;

    public function index(Request $request, MoneyCardService $cardService): Response
    {
        $cards = $cardService->listForUser($request->user())
            ->map(fn (MoneyCreditCard $card): array => [
                'id' => $card->id,
                'name' => $card->name,
                'issuer_name' => $card->issuer_name,
                'currency_code' => (string) $card->currency_code,
                'closing_day' => (string) $card->closing_day,
                'payment_day' => (string) $card->payment_day,
                'available' => $card->available_minor !== null
                    ? MoneyAmountResource::format((int) $card->available_minor, (string) $card->currency_code)
                    : null,
                'current_statement' => $card->current_statement_minor !== null
                    ? MoneyAmountResource::format((int) $card->current_statement_minor, (string) $card->currency_code)
                    : null,
                'unconfirmed' => $card->unconfirmed_minor !== null
                    ? MoneyAmountResource::format((int) $card->unconfirmed_minor, (string) $card->currency_code)
                    : null,
                'is_active' => (bool) $card->is_active,
                'lock_version' => (int) $card->lock_version,
            ]);

        return Inertia::render('Yoyu/Money/Cards/Index', [
            'cards' => $cards,
        ]);
    }

    public function store(
        StoreMoneyCardRequest $request,
        MoneyCardService $cardService,
    ): RedirectResponse {
        $data = MoneyAmountResource::castMinors($request->validated(), [
            'limit_minor',
            'available_minor',
            'current_statement_minor',
            'unconfirmed_minor',
            'revolving_balance_minor',
            'installment_balance_minor',
            'minimum_payment_minor',
        ]);
        $cardService->create($request->user(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'カードを追加しました。']);

        return redirect()->back();
    }

    public function updateSnapshot(
        UpdateMoneyCardSnapshotRequest $request,
        MoneyCreditCard $card,
        MoneyCardService $cardService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $card);

        $data = MoneyAmountResource::castMinors($request->validated(), [
            'limit_minor',
            'available_minor',
            'current_statement_minor',
            'unconfirmed_minor',
            'revolving_balance_minor',
            'installment_balance_minor',
            'minimum_payment_minor',
        ]);
        $cardService->updateSnapshot($request->user(), $card, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'カード残高を更新しました。']);

        return redirect()->back();
    }

    public function storeStatement(
        StoreMoneyCardStatementRequest $request,
        MoneyCreditCard $card,
        MoneyCardService $cardService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $card);

        $data = MoneyAmountResource::castMinors($request->validated(), ['amount_minor']);
        $cardService->createOrReviseStatement($request->user(), $card, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'カード請求を登録しました。']);

        return redirect()->back();
    }
}
