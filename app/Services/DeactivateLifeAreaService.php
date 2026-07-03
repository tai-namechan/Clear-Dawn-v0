<?php

namespace App\Services;

use App\Models\LifeArea;

class DeactivateLifeAreaService
{
    /**
     * 領域を非表示にする（is_active = false）。
     * セル・項目データは保持され、TOP Matrix から列だけが消える。
     * 物理削除・soft delete は行わない（docs/product/screens/life-areas.md）。
     */
    public function handle(LifeArea $lifeArea): LifeArea
    {
        $lifeArea->update(['is_active' => false]);

        return $lifeArea;
    }
}
