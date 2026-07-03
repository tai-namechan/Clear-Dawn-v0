<?php

namespace App\Http\Controllers;

use App\Queries\GetMatrixBoardQuery;
use App\Services\InitializeMatrixBoardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * TOP Matrix（Dashboard）を表示する。
     *
     * 初回到達時のみ InitializeMatrixBoardService が初期 Life Area とセルを生成する
     * （2 回目以降は exists 判定 1 クエリだけで書き込みは発生しない）。
     */
    public function index(
        Request $request,
        InitializeMatrixBoardService $initializeMatrixBoardService,
        GetMatrixBoardQuery $getMatrixBoardQuery,
    ): Response {
        $user = $request->user();

        $initializeMatrixBoardService->handle($user);

        $board = $getMatrixBoardQuery->handle($user);

        return Inertia::render('Dashboard', [
            'areas' => $board['areas'],
            'rows' => $board['rows'],
        ]);
    }
}
