<?php

namespace Database\Seeders;

use App\Services\EnsureMetricsService;
use Illuminate\Database\Seeder;

class MetricSeeder extends Seeder
{
    /**
     * 6 種類のメトリクスマスタを冪等に投入する。
     *
     * 実体は EnsureMetricsService（Records 画面アクセス時の自己修復と同一ロジック）。
     */
    public function run(EnsureMetricsService $ensureMetricsService): void
    {
        $ensureMetricsService->handle();
    }
}
