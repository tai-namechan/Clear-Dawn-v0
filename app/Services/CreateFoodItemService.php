<?php

namespace App\Services;

use App\Models\FoodItem;
use App\Models\User;

class CreateFoodItemService
{
    /**
     * @param  array{
     *     name: string,
     *     serving_label: string,
     *     kcal: float|int|string,
     *     protein_g: float|int|string,
     *     fat_g: float|int|string,
     *     carb_g: float|int|string
     * }  $data
     */
    public function handle(User $user, array $data): FoodItem
    {
        return FoodItem::query()->create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'serving_label' => $data['serving_label'],
            'kcal' => round((float) $data['kcal'], 2),
            'protein_g' => round((float) $data['protein_g'], 2),
            'fat_g' => round((float) $data['fat_g'], 2),
            'carb_g' => round((float) $data['carb_g'], 2),
        ]);
    }
}
