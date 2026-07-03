<?php

namespace App\Policies;

use App\Models\MatrixCellItem;
use App\Models\User;

class MatrixCellItemPolicy
{
    /**
     * 自分のセル内項目のみ編集できる。
     */
    public function update(User $user, MatrixCellItem $matrixCellItem): bool
    {
        return $this->owns($user, $matrixCellItem);
    }

    /**
     * 自分のセル内項目のみ削除できる。
     */
    public function delete(User $user, MatrixCellItem $matrixCellItem): bool
    {
        return $this->owns($user, $matrixCellItem);
    }

    /**
     * 完了切替は自分の項目、かつチェック可能な行（current）に属する場合のみ可能。
     */
    public function toggle(User $user, MatrixCellItem $matrixCellItem): bool
    {
        if (! $this->owns($user, $matrixCellItem)) {
            return false;
        }

        return $matrixCellItem->matrixCell->matrixRow->is_checkable;
    }

    private function owns(User $user, MatrixCellItem $matrixCellItem): bool
    {
        return $matrixCellItem->matrixCell->user_id === $user->id;
    }
}
