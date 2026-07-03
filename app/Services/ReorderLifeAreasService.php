<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReorderLifeAreasService
{
    /**
     * 渡された ID の並び順で sort_order を 1 から採番し直す。
     *
     * user_id スコープ付きで更新するため、他ユーザーの領域 ID が混入しても更新されない。
     * 想定件数は 1 ユーザーあたり数個〜10 個のため、ID ごとの UPDATE で問題ない。
     *
     * @param  list<string>  $orderedIds
     */
    public function handle(User $user, array $orderedIds): void
    {
        DB::transaction(function () use ($user, $orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                $user->lifeAreas()
                    ->whereKey($id)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }
}
