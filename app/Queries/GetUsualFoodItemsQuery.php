<?php

namespace App\Queries;

use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * いつもの食事用: お気に入り / 最近使った / よく使う を履歴から集計する。
 * use_count は永続化せず meal_entries から都度計算する。
 *
 * 表示順を保つため、先によく使うを確定し、最近はその余りから取る。
 */
class GetUsualFoodItemsQuery
{
    public const int FrequentDays = 90;

    public const int LimitPerSection = 12;

    /**
     * @return array{
     *     favorites: Collection<int, FoodItem>,
     *     recent: Collection<int, FoodItem>,
     *     frequent: Collection<int, FoodItem>
     * }
     */
    public function handle(User $user): array
    {
        $favorites = FoodItem::query()
            ->where('user_id', $user->id)
            ->where('is_favorite', true)
            ->orderBy('name')
            ->limit(self::LimitPerSection)
            ->get();

        $favoriteIds = $favorites->pluck('id')->all();
        $since = Carbon::now()->subDays(self::FrequentDays)->toDateString();

        $frequentRows = MealEntry::query()
            ->selectRaw('food_item_id, COUNT(*) as use_count, MAX(created_at) as last_used_at')
            ->where('user_id', $user->id)
            ->whereNotNull('food_item_id')
            ->whereDate('eaten_on', '>=', $since)
            ->when(
                $favoriteIds !== [],
                fn ($q) => $q->whereNotIn('food_item_id', $favoriteIds),
            )
            ->groupBy('food_item_id')
            ->orderByDesc('use_count')
            ->orderByDesc('last_used_at')
            ->limit(self::LimitPerSection)
            ->get();

        $frequentIds = $frequentRows->pluck('food_item_id')->all();
        $frequentFoods = $this->loadFoodsInOrder($user, $frequentIds);

        $excludeFromRecent = array_values(array_unique([...$favoriteIds, ...$frequentIds]));

        $recentRows = MealEntry::query()
            ->selectRaw('food_item_id, MAX(created_at) as last_used_at')
            ->where('user_id', $user->id)
            ->whereNotNull('food_item_id')
            ->when(
                $excludeFromRecent !== [],
                fn ($q) => $q->whereNotIn('food_item_id', $excludeFromRecent),
            )
            ->groupBy('food_item_id')
            ->orderByDesc('last_used_at')
            ->limit(self::LimitPerSection)
            ->get();

        $recentFoods = $this->loadFoodsInOrder($user, $recentRows->pluck('food_item_id')->all());

        return [
            'favorites' => $favorites,
            'recent' => $recentFoods,
            'frequent' => $frequentFoods,
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<int, FoodItem>
     */
    private function loadFoodsInOrder(User $user, array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $foods = FoodItem::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn (string $id): ?FoodItem => $foods->get($id))
            ->filter()
            ->values();
    }
}
