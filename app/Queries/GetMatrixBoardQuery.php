<?php

namespace App\Queries;

use App\Http\Resources\LifeAreaResource;
use App\Http\Resources\MatrixCellItemResource;
use App\Models\LifeArea;
use App\Models\MatrixCell;
use App\Models\MatrixRow;
use App\Models\User;

class GetMatrixBoardQuery
{
    /**
     * 認証ユーザーの TOP Matrix 表示データを組み立てる（読み取り専用）。
     *
     * クエリは固定 3 本（matrix_rows / life_areas / matrix_cells + items の eager load）。
     * 想定規模は領域数個 × 3 行 × 項目数百件のため全件取得で問題ない（er-overview.md）。
     *
     * @return array{areas: array<int, mixed>, rows: array<int, mixed>}
     */
    public function handle(User $user): array
    {
        $rows = MatrixRow::query()->orderBy('sort_order')->get();

        $areas = $user->lifeAreas()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $cells = MatrixCell::query()
            ->where('user_id', $user->id)
            ->whereIn('life_area_id', $areas->modelKeys())
            ->with(['items' => fn ($query) => $query->orderBy('sort_order')])
            ->get()
            ->keyBy(fn (MatrixCell $cell): string => $cell->life_area_id.'|'.$cell->matrix_row_id);

        return [
            'areas' => LifeAreaResource::collection($areas)->resolve(),
            'rows' => $rows->map(fn (MatrixRow $row): array => [
                'id' => $row->id,
                'key' => $row->key->value,
                'label' => $row->label,
                'is_checkable' => $row->is_checkable,
                'cells' => $areas->map(function (LifeArea $area) use ($row, $cells): array {
                    $cell = $cells->get($area->id.'|'.$row->id);

                    return [
                        'id' => $cell?->id,
                        'items' => $cell !== null
                            ? MatrixCellItemResource::collection($cell->items)->resolve()
                            : [],
                    ];
                })->values()->all(),
            ])->values()->all(),
        ];
    }
}
