<?php

namespace App\Services;

use App\Enums\LifeAreaColor;
use App\Models\MatrixRow;
use App\Models\User;
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
        private readonly CreateLifeAreaService $createLifeAreaService,
    ) {}

    /**
     * 初回 Dashboard 到達時に初期 Life Area と固定 3 行分の Matrix Cell を生成する。
     *
     * 冪等性: 領域が 1 つでも存在すれば（非表示・soft delete 済み含む）何もしない。
     * 判定は exists() の 1 クエリだけなので、2 回目以降の Dashboard 表示に書き込みは発生しない。
     */
    public function handle(User $user): void
    {
        if ($user->lifeAreas()->withTrashed()->exists()) {
            return;
        }

        DB::transaction(function () use ($user): void {
            $rows = MatrixRow::query()->orderBy('sort_order')->get();

            foreach (self::DEFAULT_LIFE_AREAS as $area) {
                $this->createLifeAreaService->handle($user, $area['name'], $area['color'], $rows);
            }
        });
    }
}
