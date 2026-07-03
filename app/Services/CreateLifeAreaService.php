<?php

namespace App\Services;

use App\Enums\LifeAreaColor;
use App\Models\LifeArea;
use App\Models\MatrixRow;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateLifeAreaService
{
    /**
     * 領域を作成し、固定行ぶんの Matrix Cell を同時に生成する
     * （docs/product/screens/life-areas.md「追加 = 固定行ぶんのセルを利用可能にする」）。
     *
     * sort_order はサーバー側で採番する（conventions.md）。
     *
     * @param  Collection<int, MatrixRow>|null  $rows  呼び出し側で取得済みの固定行（未指定なら取得する）
     */
    public function handle(User $user, string $name, LifeAreaColor $color, ?Collection $rows = null): LifeArea
    {
        return DB::transaction(function () use ($user, $name, $color, $rows): LifeArea {
            $rows ??= MatrixRow::query()->orderBy('sort_order')->get();

            $nextSortOrder = (int) $user->lifeAreas()->withTrashed()->max('sort_order') + 1;

            $lifeArea = $user->lifeAreas()->create([
                'name' => $name,
                'color' => $color,
                'sort_order' => $nextSortOrder,
                'is_active' => true,
            ]);

            foreach ($rows as $row) {
                $lifeArea->matrixCells()->create([
                    'user_id' => $user->id,
                    'matrix_row_id' => $row->id,
                ]);
            }

            return $lifeArea;
        });
    }
}
