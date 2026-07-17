<?php

namespace App\Http\Controllers\Yoyu\Money;

use App\Domain\Yoyu\Money\Services\MoneyAnalysisQuery;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MoneyAnalysisController extends Controller
{
    public function index(
        Request $request,
        MoneyAnalysisQuery $analysisQuery,
        UserTimezoneResolver $timezoneResolver,
    ): Response {
        $user = $request->user();
        $timezone = $timezoneResolver->for($user);
        $now = CarbonImmutable::now($timezone);

        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        if (preg_match('/^\d{4}-\d{2}$/', $from) === 1) {
            $from = CarbonImmutable::parse($from.'-01', $timezone)->startOfMonth()->toDateString();
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1) {
            $from = $now->startOfMonth()->subMonthsNoOverflow(5)->toDateString();
        }

        if (preg_match('/^\d{4}-\d{2}$/', $to) === 1) {
            $to = CarbonImmutable::parse($to.'-01', $timezone)->endOfMonth()->toDateString();
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1) {
            $to = $now->endOfMonth()->toDateString();
        }

        $filters = array_filter([
            'category_id' => $request->input('category_id'),
            'counterparty_id' => $request->input('counterparty_id'),
            'account_id' => $request->input('account_id'),
        ], fn (mixed $value): bool => is_string($value) && $value !== '');

        return Inertia::render('Yoyu/Money/Analysis/Index', $analysisQuery->analyze(
            $user,
            $from,
            $to,
            $filters,
        ));
    }
}
