<?php

namespace App\Services;

use App\Enums\MatrixRowKey;
use App\Models\MatrixRow;
use Illuminate\Database\Eloquent\Collection;

class EnsureMatrixRowsService
{
    /**
     * 固定 3 行の matrix_rows を冪等に ensure する（MatrixRowKey が正）。
     *
     * key を一意キーに updateOrCreate するため、何度実行しても 3 行のまま。
     * seed 漏れ・行欠損があっても呼び出し時点で復旧する。
     *
     * @return Collection<int, MatrixRow> sort_order 順の固定 3 行
     */
    public function handle(): Collection
    {
        foreach (MatrixRowKey::cases() as $key) {
            MatrixRow::query()->updateOrCreate(
                ['key' => $key->value],
                [
                    'label' => $key->label(),
                    'sort_order' => $key->sortOrder(),
                    'is_checkable' => $key->isCheckable(),
                ],
            );
        }

        return MatrixRow::query()->orderBy('sort_order')->get();
    }
}
