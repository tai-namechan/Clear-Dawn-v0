<?php

namespace App\Services;

use App\Models\FoodItem;

class ToggleFoodItemFavoriteService
{
    public function handle(FoodItem $foodItem, bool $isFavorite): FoodItem
    {
        $foodItem->is_favorite = $isFavorite;
        $foodItem->save();

        return $foodItem->fresh() ?? $foodItem;
    }
}
