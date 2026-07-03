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
     * InitializeMatrixBoardService が固定行・初期 Life Area・セル欠損を自己修復する
     * （整合済みなら軽量な読み取り判定のみで書き込みは発生しない）。
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
