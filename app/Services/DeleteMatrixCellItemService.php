<?php

namespace App\Services;

use App\Models\MatrixCellItem;

class DeleteMatrixCellItemService
{
    /**
     * セル内項目を soft delete する（tables.md: matrix_cell_items は soft delete 対象）。
     */
    public function handle(MatrixCellItem $item): void
    {
        $item->delete();
    }
}
