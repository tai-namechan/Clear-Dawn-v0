<?php

namespace Database\Seeders;

use App\Enums\MatrixRowKey;
use App\Models\MatrixRow;
use Illuminate\Database\Seeder;

class MatrixRowSeeder extends Seeder
{
    /**
     * 固定 3 行の matrix_rows を冪等に投入する。
     * key を一意キーに updateOrCreate するため、何度実行しても 3 行のまま。
     */
    public function run(): void
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
    }
}
