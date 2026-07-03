<?php

namespace App\Services;

use App\Models\MatrixCellItem;

class UpdateMatrixCellItemService
{
    /**
     * セル内項目の題名・メモを更新する。完了状態は Toggle 専用 Service でのみ変更する。
     */
    public function handle(MatrixCellItem $item, string $title, ?string $memo): MatrixCellItem
    {
        $item->update([
            'title' => $title,
            'memo' => $memo,
        ]);

        return $item;
    }
}
