<?php

namespace App\Http\Controllers;

use App\Enums\LifeAreaColor;
use App\Http\Requests\LifeAreas\ReorderLifeAreasRequest;
use App\Http\Requests\LifeAreas\StoreLifeAreaRequest;
use App\Http\Requests\LifeAreas\UpdateLifeAreaRequest;
use App\Http\Resources\LifeAreaResource;
use App\Models\LifeArea;
use App\Services\CreateLifeAreaService;
use App\Services\DeactivateLifeAreaService;
use App\Services\ReactivateLifeAreaService;
use App\Services\ReorderLifeAreasService;
use App\Services\UpdateLifeAreaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LifeAreaController extends Controller
{
    /**
     * 領域管理画面（非表示の領域も含む一覧）。
     * 想定件数は 1 ユーザーあたり数個〜10 個のため全件取得で問題ない。
     */
    public function index(Request $request): Response
    {
        $lifeAreas = $request->user()->lifeAreas()
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('LifeAreas/Index', [
            'lifeAreas' => LifeAreaResource::collection($lifeAreas)->resolve(),
        ]);
    }

    /**
     * 領域を追加する（固定行ぶんの Matrix Cell も同時に生成される）。
     */
    public function store(StoreLifeAreaRequest $request, CreateLifeAreaService $service): RedirectResponse
    {
        $validated = $request->validated();

        $service->handle(
            $request->user(),
            $validated['name'],
            LifeAreaColor::from($validated['color']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => '領域を追加しました。']);

        return back();
    }

    /**
     * 領域の名称・色を変更する。
     */
    public function update(
        UpdateLifeAreaRequest $request,
        LifeArea $lifeArea,
        UpdateLifeAreaService $service,
    ): RedirectResponse {
        Gate::authorize('update', $lifeArea);

        $validated = $request->validated();

        $service->handle(
            $lifeArea,
            $validated['name'],
            LifeAreaColor::from($validated['color']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => '領域を更新しました。']);

        return back();
    }

    /**
     * 領域を並び替える（sort_order はサーバー側で採番）。
     */
    public function reorder(ReorderLifeAreasRequest $request, ReorderLifeAreasService $service): RedirectResponse
    {
        $service->handle($request->user(), $request->validated()['ordered_ids']);

        return back();
    }

    /**
     * 領域を非表示にする（DELETE だが物理削除・soft delete はしない。
     * is_active = false による非表示が運用の既定: docs/product/screens/life-areas.md）。
     */
    public function destroy(LifeArea $lifeArea, DeactivateLifeAreaService $service): RedirectResponse
    {
        Gate::authorize('update', $lifeArea);

        $service->handle($lifeArea);

        Inertia::flash('toast', ['type' => 'success', 'message' => '領域を非表示にしました。']);

        return back();
    }

    /**
     * 非表示の領域を再表示する。
     */
    public function restore(LifeArea $lifeArea, ReactivateLifeAreaService $service): RedirectResponse
    {
        Gate::authorize('update', $lifeArea);

        $service->handle($lifeArea);

        Inertia::flash('toast', ['type' => 'success', 'message' => '領域を再表示しました。']);

        return back();
    }
}
