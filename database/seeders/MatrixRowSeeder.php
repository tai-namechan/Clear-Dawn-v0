<?php

namespace Database\Seeders;

use App\Services\EnsureMatrixRowsService;
use Illuminate\Database\Seeder;

class MatrixRowSeeder extends Seeder
{
    /**
     * 固定 3 行の matrix_rows を冪等に投入する。
     *
     * 実体は EnsureMatrixRowsService（Dashboard 初回アクセスの自己修復と同一ロジック）。
     * 本番での seed 実行は補助であり、未実行でもアプリ側で復旧する。
     */
    public function run(EnsureMatrixRowsService $ensureMatrixRowsService): void
    {
        $ensureMatrixRowsService->handle();
    }
}
