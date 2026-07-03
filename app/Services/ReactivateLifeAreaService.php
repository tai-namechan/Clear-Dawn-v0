<?php

namespace App\Services;

use App\Models\LifeArea;

class ReactivateLifeAreaService
{
    /**
     * 非表示の領域を再表示する（is_active = true）。列とデータが復帰する。
     */
    public function handle(LifeArea $lifeArea): LifeArea
    {
        $lifeArea->update(['is_active' => true]);

        return $lifeArea;
    }
}
