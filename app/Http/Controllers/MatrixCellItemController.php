<?php

namespace App\Http\Controllers;

use App\Http\Requests\Matrix\StoreMatrixCellItemRequest;
use App\Http\Requests\Matrix\UpdateMatrixCellItemRequest;
use App\Models\MatrixCell;
use App\Models\MatrixCellItem;
use App\Services\AddMatrixCellItemService;
use App\Services\DeleteMatrixCellItemService;
use App\Services\ToggleMatrixCellItemCompletionService;
use App\Services\UpdateMatrixCellItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MatrixCellItemController extends Controller
{
    /**
     * セルに項目を追加する。
     */
    public function store(
        StoreMatrixCellItemRequest $request,
        MatrixCell $matrixCell,
        AddMatrixCellItemService $service,
    ): RedirectResponse {
        Gate::authorize('addItem', $matrixCell);

        $validated = $request->validated();

        $service->handle($matrixCell, $validated['title'], $validated['memo'] ?? null);

        return back();
    }

    /**
     * セル内項目の題名・メモを更新する。
     */
    public function update(
        UpdateMatrixCellItemRequest $request,
        MatrixCellItem $matrixCellItem,
        UpdateMatrixCellItemService $service,
    ): RedirectResponse {
        Gate::authorize('update', $matrixCellItem);

        $validated = $request->validated();

        $service->handle($matrixCellItem, $validated['title'], $validated['memo'] ?? null);

        return back();
    }

    /**
     * 完了状態を切り替える（checkable 行のみ。Policy で判定）。
     */
    public function toggle(
        MatrixCellItem $matrixCellItem,
        ToggleMatrixCellItemCompletionService $service,
    ): RedirectResponse {
        Gate::authorize('toggle', $matrixCellItem);

        $service->handle($matrixCellItem);

        return back();
    }

    /**
     * セル内項目を削除する（soft delete）。
     */
    public function destroy(
        MatrixCellItem $matrixCellItem,
        DeleteMatrixCellItemService $service,
    ): RedirectResponse {
        Gate::authorize('delete', $matrixCellItem);

        $service->handle($matrixCellItem);

        return back();
    }
}
