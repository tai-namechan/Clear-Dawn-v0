<?php

namespace App\Http\Controllers\Yoyu\Money;

use App\Domain\Yoyu\Money\Models\MoneySimulation;
use App\Domain\Yoyu\Money\Services\MoneySimulationService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Yoyu\Money\Concerns\EnsuresMoneyOwnership;
use App\Http\Requests\Yoyu\Money\StoreMoneySimulationActionRequest;
use App\Http\Requests\Yoyu\Money\StoreMoneySimulationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MoneySimulationController extends Controller
{
    use EnsuresMoneyOwnership;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $simulations = MoneySimulation::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (MoneySimulation $simulation): array => [
                'id' => $simulation->id,
                'name' => $simulation->name,
                'status' => $simulation->status->value,
                'base_date' => (string) $simulation->base_date?->toDateString(),
                'horizon_months' => (int) $simulation->horizon_months,
                'created_at' => $simulation->created_at?->toIso8601String(),
                'result_payload' => $simulation->result_payload,
            ]);

        return Inertia::render('Yoyu/Money/Simulations/Index', [
            'simulations' => $simulations,
        ]);
    }

    public function store(
        StoreMoneySimulationRequest $request,
        MoneySimulationService $simulationService,
    ): RedirectResponse {
        $simulationService->create($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'シミュレーションを作成しました。']);

        return redirect()->back();
    }

    public function storeAction(
        StoreMoneySimulationActionRequest $request,
        MoneySimulation $simulation,
        MoneySimulationService $simulationService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $simulation);

        $simulationService->addAction($request->user(), $simulation, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'アクションを追加しました。']);

        return redirect()->back();
    }

    public function calculate(
        Request $request,
        MoneySimulation $simulation,
        MoneySimulationService $simulationService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $simulation);

        $simulationService->calculate($request->user(), $simulation);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'シミュレーションを計算しました。']);

        return redirect()->back();
    }

    public function apply(
        Request $request,
        MoneySimulation $simulation,
        MoneySimulationService $simulationService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $simulation);

        $simulationService->apply($request->user(), $simulation);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'シミュレーションを適用しました。']);

        return redirect()->back();
    }

    public function discard(
        Request $request,
        MoneySimulation $simulation,
        MoneySimulationService $simulationService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $simulation);

        $simulationService->discard($request->user(), $simulation);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'シミュレーションを破棄しました。']);

        return redirect()->back();
    }
}
