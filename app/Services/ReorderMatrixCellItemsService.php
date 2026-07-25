<?php

namespace App\Services;

use App\Models\MatrixCell;
use Illuminate\Support\Facades\DB;

class ReorderMatrixCellItemsService
{
    /**
     * 渡された ID の並び順で sort_order を 1 から採番し直す。
     * セル所有の items にスコープするため、他セル / 他ユーザーの ID は更新されない。
     *
     * @param  list<string>  $orderedIds
     */
    public function handle(MatrixCell $matrixCell, array $orderedIds): void
    {
        DB::transaction(function () use ($matrixCell, $orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                $matrixCell->items()
                    ->whereKey($id)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }
}
