<?php

namespace App\Http\Controllers\Yoyu\Money;

use App\Domain\Yoyu\Money\Services\MoneyMonthlySnapshotService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Yoyu\Money\CloseMoneyMonthRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class MoneyMonthlySnapshotController extends Controller
{
    public function close(
        CloseMoneyMonthRequest $request,
        string $month,
        MoneyMonthlySnapshotService $snapshotService,
    ): RedirectResponse {
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $month) === 1, 404);

        $snapshotService->closeMonth($request->user(), $month);

        Inertia::flash('toast', ['type' => 'success', 'message' => '月次を締めました。']);

        return redirect()->back();
    }
}
