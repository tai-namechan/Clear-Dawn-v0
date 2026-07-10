<?php

namespace App\Queries;

use App\Models\FoodItem;
use App\Models\User;
use Illuminate\Support\Collection;

class SearchFoodItemsQuery
{
    public const int Limit = 20;

    /**
     * user スコープ + name 部分一致 + limit 20 + 更新日降順。
     *
     * @return Collection<int, FoodItem>
     */
    public function handle(User $user, ?string $query = null): Collection
    {
        return FoodItem::query()
            ->where('user_id', $user->id)
            ->when(
                $query !== null && trim($query) !== '',
                fn ($builder) => $builder->where('name', 'like', '%'.trim($query).'%'),
            )
            ->orderByDesc('updated_at')
            ->limit(self::Limit)
            ->get();
    }
}
