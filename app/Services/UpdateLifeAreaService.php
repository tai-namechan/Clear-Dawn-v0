<?php

namespace App\Services;

use App\Enums\LifeAreaColor;
use App\Models\LifeArea;

class UpdateLifeAreaService
{
    /**
     * 領域の名称・色を変更する。既存セル・項目は保持される（表示のみ変わる）。
     */
    public function handle(LifeArea $lifeArea, string $name, LifeAreaColor $color): LifeArea
    {
        $lifeArea->update([
            'name' => $name,
            'color' => $color,
        ]);

        return $lifeArea;
    }
}
