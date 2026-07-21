<?php

namespace App\Http\Controllers\Yoyu\Money;

use App\Domain\Yoyu\Money\Enums\MoneyCashflowStatus;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Services\MoneyCashflowService;
use App\Domain\Yoyu\Money\Services\MoneyProjectionQuery;
use App\Domain\Yoyu\Money\Services\MoneyReconciliationService;
use App\Domain\Yoyu\Money\Support\MoneyAmount;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Yoyu\Money\Concerns\EnsuresMoneyOwnership;
use App\Http\Requests\Yoyu\Money\DeferMoneyCashflowRequest;
use App\Http\Requests\Yoyu\Money\DestroyMoneyCashflowRequest;
use App\Http\Requests\Yoyu\Money\SettleMoneyCashflowRequest;
use App\Http\Requests\Yoyu\Money\StoreMoneyCashflowRequest;
use App\Http\Resources\Yoyu\Money\MoneyAmountResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MoneyCashflowController extends Controller
{
    use EnsuresMoneyOwnership;

    public function index(
        Request $request,
        MoneyReconciliationService $reconciliationService,
        MoneyProjectionQuery $projectionQuery,
        UserTimezoneResolver $timezoneResolver,
    ): Response {
        $user = $request->user();
        $timezone = $timezoneResolver->for($user);
        $now = CarbonImmutable::now($timezone);

        $month = $request->string('month')->toString();
        $month = preg_match('/^\d{4}-\d{2}$/', $month) === 1 ? $month : $now->format('Y-m');
        $monthStart = CarbonImmutable::parse($month.'-01', $timezone)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth()->toDateString();

        $cashflows = MoneyCashflow::query()
            ->withoutUserScope()
            ->with(['category', 'counterparty'])
            ->where('user_id', $user->id)
            ->whereNotIn('status', [
                MoneyCashflowStatus::Canceled->value,
                MoneyCashflowStatus::Deferred->value,
            ])
            ->whereDate('due_on', '>=', $monthStart->toDateString())
            ->whereDate('due_on', '<=', $monthEnd)
            ->orderBy('due_on')
            ->limit(500)
            ->get();

        // Running balance: start from dashboard funds, apply events up to each row.
        $projection = $projectionQuery->forUser($user, $month);
        $fundsMinor = (int) $projection['funds_minor'];
        $timelineById = collect($projection['balance_timeline'] ?? [])
            ->keyBy('id');

        $rows = $cashflows->map(function (MoneyCashflow $cashflow) use ($reconciliationService, $timelineById): array {
            $remaining = $reconciliationService->remainingAmountMinor($cashflow);
            $timeline = $timelineById->get($cashflow->id);

            return [
                'id' => $cashflow->id,
                'name' => $cashflow->name,
                'direction' => $cashflow->direction->value,
                'kind' => $cashflow->kind->value,
                'status' => $cashflow->status->value,
                'certainty' => $cashflow->certainty->value,
                'flexibility' => $cashflow->flexibility->value,
                'due_on' => (string) $cashflow->due_on?->toDateString(),
                'amount' => MoneyAmountResource::format(
                    (int) $cashflow->amount_minor,
                    (string) $cashflow->currency_code,
                ),
                'remaining_minor' => MoneyAmount::ofMinor($remaining)->toString(),
                'balance_after_minor' => is_array($timeline)
                    ? ($timeline['balance_after_minor'] ?? null)
                    : null,
                'category_name' => $cashflow->category?->name,
                'counterparty_name' => $cashflow->counterparty?->name,
                'payment_method' => $cashflow->payment_method?->value,
                'is_essential' => (bool) ($cashflow->category?->is_essential ?? false),
                'lock_version' => (int) $cashflow->lock_version,
            ];
        });

        return Inertia::render('Yoyu/Money/Cashflows/Index', [
            'cashflows' => $rows,
            'month' => $month,
            'as_of' => $now->toDateString(),
            'funds_minor' => MoneyAmount::ofMinor($fundsMinor)->toString(),
            'compose' => $request->string('compose')->toString() ?: null,
            'highlight' => $request->string('highlight')->toString() ?: null,
            'filters' => [
                'direction' => $request->string('direction')->toString() ?: 'all',
                'status' => $request->string('status')->toString() ?: 'all',
                'certainty' => $request->string('certainty')->toString() ?: 'all',
                'q' => $request->string('q')->toString() ?: '',
                'sort' => $request->string('sort')->toString() ?: 'date',
            ],
        ]);
    }

    public function store(
        StoreMoneyCashflowRequest $request,
        MoneyCashflowService $cashflowService,
    ): RedirectResponse {
        $data = MoneyAmountResource::castMinors($request->validated(), ['amount_minor']);

        // Infer kind from direction when UI only asks income vs payment.
        if (! isset($data['kind'])) {
            $data['kind'] = ($data['direction'] ?? '') === MoneyDirection::Inflow->value
                ? 'income'
                : 'expense';
        }

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
