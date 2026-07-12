<?php

namespace App\Domain\Yoyu\Services;

use App\Domain\Yoyu\Data\ClearDawnHand;
use App\Enums\MatrixRowKey;
use App\Models\MatrixCellItem;
use App\Models\User;

/**
 * Selects one incomplete MatrixRowKey::Current item. Deterministic — no AI.
 *
 * Order: life_areas.sort_order → item.sort_order → created_at → id.
 * YoyuFocusItem is mind-dump state, not an explicit hand — not used here.
 */
final class ClearDawnHandService
{
    public function forUser(User $user): ?ClearDawnHand
    {
        $item = MatrixCellItem::query()
            ->select('matrix_cell_items.*')
            ->join('matrix_cells', 'matrix_cells.id', '=', 'matrix_cell_items.matrix_cell_id')
            ->join('matrix_rows', 'matrix_rows.id', '=', 'matrix_cells.matrix_row_id')
            ->join('life_areas', 'life_areas.id', '=', 'matrix_cells.life_area_id')
            ->where('matrix_cells.user_id', $user->id)
            ->where('life_areas.user_id', $user->id)
            ->where('matrix_rows.key', MatrixRowKey::Current->value)
            ->where('matrix_cell_items.is_completed', false)
            ->whereNull('matrix_cell_items.deleted_at')
            ->whereNull('life_areas.deleted_at')
            ->where('life_areas.is_active', true)
            ->with(['matrixCell.lifeArea'])
            ->orderBy('life_areas.sort_order')
            ->orderBy('matrix_cell_items.sort_order')
            ->orderBy('matrix_cell_items.created_at')
            ->orderBy('matrix_cell_items.id')
            ->first();

        if ($item === null) {
            return null;
        }

        $lifeArea = $item->matrixCell->lifeArea;
        if ($lifeArea === null) {
            return null;
        }

        return new ClearDawnHand(
            id: (string) $item->id,
            title: (string) $item->title,
            lifeAreaName: (string) $lifeArea->name,
            lifeAreaId: (string) $lifeArea->id,
            sortOrder: (int) $item->sort_order,
        );
    }
}
