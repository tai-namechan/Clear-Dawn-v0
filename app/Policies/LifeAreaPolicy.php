<?php

namespace App\Policies;

use App\Models\LifeArea;
use App\Models\User;

class LifeAreaPolicy
{
    /**
     * 自分の領域のみ更新（名称・色変更 / 並び替え / 非表示・再表示）できる。
     */
    public function update(User $user, LifeArea $lifeArea): bool
    {
        return $lifeArea->user_id === $user->id;
    }
}
