<?php

namespace App\Services;

use App\Enums\LifeAreaColor;
use App\Enums\MatrixRowKey;
use App\Models\MatrixCell;
use App\Models\MatrixRow;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InitializeMatrixBoardService
{
    /**
     * 初期 Life Area（tables.md / top-matrix.md の既定 4 領域）。
     *
     * @var array<int, array{name: string, color: LifeAreaColor}>
     */
    private const DEFAULT_LIFE_AREAS = [
        ['name' => '仕事', 'color' => LifeAreaColor::Dawn],
        ['name' => '野球', 'color' => LifeAreaColor::Moss],
        ['name' => 'バイオリン', 'color' => LifeAreaColor::Gilt],
        ['name' => 'プライベート', 'color' => LifeAreaColor::Sunrise],
    ];

    public function __construct(
        private readonly EnsureMatrixRowsService $ensureMatrixRowsService,
        private readonly CreateLifeAreaService $createLifeAreaService,
    ) {}

    /**
     * Dashboard 到達時に TOP Matrix のデータ整合性を保証する（自己修復）。
     *
     * 1 トランザクションで以下を行う:
     * 1. 固定 3 行の matrix_rows を冪等に ensure（seed 漏れがあっても復旧）
     * 2. 初回（過去に領域を一度も作っていない）のみデフォルト 4 領域を生成
     * 3. soft delete されていない Life Area ごとに固定行ぶんの Matrix Cell 不足を補完
     *
     * 既存の項目・完了状態・並び順には触れない。
     * 整合済みなら読み取りだけで即 return し、書き込みは発生しない。
     */
    public function handle(User $user): void
    {
        if ($this->isConsistent($user)) {
            return;
        }

        DB::transaction(function () use ($user): void {
            // 同一ユーザーの並行初回アクセスでデフォルト領域が二重生成されないよう
            // User 行をロックしてから判定する（先行トランザクションの commit を待つ）
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $rows = $this->ensureMatrixRowsService->handle();

            if (! $user->lifeAreas()->withTrashed()->exists()) {
                // 初回のみ。過去に領域を作成・非表示・soft delete したユーザーには再生成しない
                foreach (self::DEFAULT_LIFE_AREAS as $area) {
                    $this->createLifeAreaService->handle($user, $area['name'], $area['color'], $rows);
                }

                return;
            }

            $this->reconcileMissingCells($user, $rows);
        });
    }

    /**
     * matrix_rows が揃い、soft delete されていない全領域に固定行ぶんのセルがあるか。
     *
     * Dashboard 表示ごとに走るため、count / exists の軽量クエリのみで判定する。
     */
    private function isConsistent(User $user): bool
    {
        $rowCount = count(MatrixRowKey::cases());

        if (MatrixRow::query()->count() !== $rowCount) {
            return false;
        }

        if (! $user->lifeAreas()->withTrashed()->exists()) {
            return false;
        }

        $liveAreaCount = $user->lifeAreas()->count();

        // (user_id, life_area_id, matrix_row_id) の unique 制約により
        // セル数が「領域数 × 固定行数」と一致すれば欠損なしと言い切れる
        $cellCount = $user->matrixCells()
            ->whereIn('life_area_id', $user->lifeAreas()->select('id'))
            ->count();

        return $cellCount === $liveAreaCount * $rowCount;
    }

    /**
     * soft delete されていない Life Area（active / inactive とも）のセル欠損を補完する。
     *
     * @param  Collection<int, MatrixRow>  $rows
     */
    private function reconcileMissingCells(User $user, Collection $rows): void
    {
        $areas = $user->lifeAreas()->get();

        $existingKeys = $user->matrixCells()
            ->whereIn('life_area_id', $areas->modelKeys())
            ->get(['life_area_id', 'matrix_row_id'])
            ->map(fn (MatrixCell $cell): string => $cell->life_area_id.'|'.$cell->matrix_row_id)
            ->flip();

        foreach ($areas as $area) {
            foreach ($rows as $row) {
                if ($existingKeys->has($area->id.'|'.$row->id)) {
                    continue;
                }

                // unique 制約下でも安全に補完する（既に存在すれば何もしない）
                MatrixCell::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'life_area_id' => $area->id,
                    'matrix_row_id' => $row->id,
                ]);
            }
        }
    }
}
