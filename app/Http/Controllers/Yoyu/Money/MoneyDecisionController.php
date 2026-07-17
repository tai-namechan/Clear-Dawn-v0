<?php

namespace App\Http\Controllers\Yoyu\Money;

use App\Domain\Yoyu\Money\Models\MoneyDecision;
use App\Domain\Yoyu\Money\Services\MoneyDecisionService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Yoyu\Money\Concerns\EnsuresMoneyOwnership;
use App\Http\Requests\Yoyu\Money\ReviewMoneyDecisionRequest;
use App\Http\Requests\Yoyu\Money\StoreMoneyDecisionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MoneyDecisionController extends Controller
{
    use EnsuresMoneyOwnership;

    public function index(Request $request, MoneyDecisionService $decisionService): Response
    {
        $decisions = $decisionService->list($request->user())
            ->map(fn (MoneyDecision $decision): array => [
                'id' => $decision->id,
                'title' => $decision->title,
                'status' => $decision->status->value,
                'decided_on' => (string) $decision->decided_on?->toDateString(),
                'reviewed_at' => $decision->reviewed_at?->toIso8601String(),
                'memo' => $decision->memo,
            ]);

        return Inertia::render('Yoyu/Money/Decisions/Index', [
            'decisions' => $decisions,
        ]);
    }

    public function store(
        StoreMoneyDecisionRequest $request,
        MoneyDecisionService $decisionService,
    ): RedirectResponse {
        $decisionService->createManual($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => '判断を記録しました。']);

        return redirect()->back();
    }

    public function review(
        ReviewMoneyDecisionRequest $request,
        MoneyDecision $decision,
        MoneyDecisionService $decisionService,
    ): RedirectResponse {
        $this->ensureOwned($request->user(), $decision);

        $data = $request->validated();
        $decisionService->review(
            $request->user(),
            $decision,
            $data['actual_effect_payload'],
            isset($data['reflection']) ? (string) $data['reflection'] : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => '判断を振り返りました。']);

        return redirect()->back();
    }
}
