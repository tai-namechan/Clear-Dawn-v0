<?php

namespace App\Policies;

use App\Models\MatrixCell;
use App\Models\User;

class MatrixCellPolicy
{
    /**
     * 自分のセルにのみ項目を追加できる。
     */
    public function addItem(User $user, MatrixCell $matrixCell): bool
    {
        return $matrixCell->user_id === $user->id;
    }
}
