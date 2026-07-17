<?php

namespace App\Http\Controllers\Yoyu\Money;

use App\Domain\Yoyu\Money\Enums\MoneyCashflowStatus;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Services\MoneyCashflowService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Yoyu\Money\Concerns\EnsuresMoneyOwnership;
use App\Http\Requests\Yoyu\Money\DeferMoneyCashflowRequest;
use App\Http\Requests\Yoyu\Money\DestroyMoneyCashflowRequest;
use App\Http\Requests\Yoyu\Money\SettleMoneyCashflowRequest;
use App\Http\Requests\Yoyu\Money\StoreMoneyCashflowRequest;
use App\Http\Resources\Yoyu\Money\MoneyAmountResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MoneyCashflowController extends Controller
{
    use EnsuresMoneyOwnership;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $cashflows = MoneyCashflow::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->whereNotIn('status', [
                MoneyCashflowStatus::Canceled->value,
                MoneyCashflowStatus::Deferred->value,
            ])
            ->orderBy('due_on')
            ->limit(500)
            ->get()
            ->map(fn (MoneyCashflow $cashflow): array => [
                'id' => $cashflow->id,
                'name' => $cashflow->name,
                'direction' => $cashflow->direction->value,
                'kind' => $cashflow->kind->value,
                'status' => $cashflow->status->value,
                'certainty' => $cashflow->certainty->value,
                'due_on' => (string) $cashflow->due_on?->toDateString(),
                'amount' => MoneyAmountResource::format(
                    (int) $cashflow->amount_minor,
                    (string) $cashflow->currency_code,
                ),
                'lock_version' => (int) $cashflow->lock_version,
            ]);

        return Inertia::render('Yoyu/Money/Cashflows/Index', [
            'cashflows' => $cashflows,
        ]);
    }

    public function store(
        StoreMoneyCashflowRequest $request,
        MoneyCashflowService $cashflowService,
    ): RedirectResponse {
        $data = MoneyAmountResource::castMinors($request->validated(), ['amount_minor']);
        $cashflowService->create($request->user(), $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => '予定を追加しました。']);

        return redirect()->back();
    }

    public function settle(
        SettleMoneyCashflowRequest $request,
        MoneyCashflow $cashflow,
        MoneyCashflowService $cashflowService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $cashflow);

        $data = MoneyAmountResource::castMinors($request->validated(), ['amount_minor']);
        $cashflowService->settle($request->user(), $cashflow, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => '予定を消し込みました。']);

        return redirect()->back();
    }

    public function defer(
        DeferMoneyCashflowRequest $request,
        MoneyCashflow $cashflow,
        MoneyCashflowService $cashflowService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $cashflow);

        $data = $request->validated();
        $cashflowService->defer(
            $request->user(),
            $cashflow,
            (string) $data['due_on'],
            (int) $data['lock_version'],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => '予定を延期しました。']);

        return redirect()->back();
    }

    public function destroy(
        DestroyMoneyCashflowRequest $request,
        MoneyCashflow $cashflow,
        MoneyCashflowService $cashflowService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $cashflow);

        $cashflowService->cancel(
            $request->user(),
            $cashflow,
            (int) $request->validated('lock_version'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => '予定を取り消しました。']);

        return redirect()->back();
    }
}
