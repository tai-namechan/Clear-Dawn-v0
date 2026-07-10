<?php

namespace App\Services;

use App\Models\FoodItem;

class DeleteFoodItemService
{
    public function handle(FoodItem $foodItem): void
    {
        $foodItem->delete();
    }
}
