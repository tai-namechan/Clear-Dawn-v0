<?php

namespace App\Http\Controllers\Yoyu\Money;

use App\Domain\Yoyu\Money\Services\MoneyProjectionQuery;
use App\Domain\Yoyu\Money\Services\MoneySetupService;
use App\Domain\Yoyu\Money\Services\RecurringCashflowGenerator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MoneyDashboardController extends Controller
{
    public function index(
        Request $request,
        MoneySetupService $setupService,
        RecurringCashflowGenerator $recurringCashflowGenerator,
        MoneyProjectionQuery $projectionQuery,
    ): Response {
        $user = $request->user();
        $setupService->ensureForUser($user);
        $recurringCashflowGenerator->generateForUser($user);

        $month = $request->string('month')->toString();
        $month = preg_match('/^\d{4}-\d{2}$/', $month) === 1 ? $month : null;

        return Inertia::render('Yoyu/Money/Dashboard', $projectionQuery->forUser($user, $month));
    }
}
