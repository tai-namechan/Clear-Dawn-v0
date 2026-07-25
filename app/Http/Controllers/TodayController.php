<?php

namespace App\Http\Controllers;

use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Http\Requests\Today\ShowTodayRequest;
use App\Services\EvaluateRulesForDayService;
use App\Services\GenerateProgramDayPlansService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class TodayController extends Controller
{
    /**
     * 旧「今日/作戦」画面。作戦・チェックインは /routines 今日タブへ巻き取り済み。
     * ブックマーク互換のためリダイレクトする（プラン生成・ルール評価は Routines 側でも実行）。
     */
    public function index(
        ShowTodayRequest $request,
        GenerateProgramDayPlansService $generateProgramDayPlans,
        EvaluateRulesForDayService $evaluateRules,
        UserTimezoneResolver $timezoneResolver,
    ): RedirectResponse {
        $user = $request->user();
        $today = $timezoneResolver->todayDateString($user);
        $targetDate = Carbon::parse($request->validated('date') ?? $today);

        $generateProgramDayPlans->handle($user, $targetDate);
        $evaluateRules->handle($user, $targetDate);

        return redirect()->route('routines.index');
    }
}
