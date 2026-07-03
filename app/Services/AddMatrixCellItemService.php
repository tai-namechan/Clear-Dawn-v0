<?php

namespace App\Services;

use App\Models\MatrixCell;
use App\Models\MatrixCellItem;

class AddMatrixCellItemService
{
    /**
     * セルに項目を追加する。sort_order はサーバー側で末尾に採番する（conventions.md）。
     */
    public function handle(MatrixCell $matrixCell, string $title, ?string $memo): MatrixCellItem
    {
        $nextSortOrder = (int) $matrixCell->items()->withTrashed()->max('sort_order') + 1;

        return $matrixCell->items()->create([
            'title' => $title,
            'memo' => $memo,
            'sort_order' => $nextSortOrder,
        ]);
    }
}
