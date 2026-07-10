<?php

namespace App\Services;

use App\Models\NutritionGoal;
use App\Models\User;

class UpsertNutritionGoalsService
{
    /**
     * @param  array{
     *     kcal: float|int|string,
     *     protein_g: float|int|string,
     *     fat_g: float|int|string,
     *     carb_g: float|int|string
     * }  $data
     */
    public function handle(User $user, array $data): NutritionGoal
    {
        return NutritionGoal::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'kcal' => round((float) $data['kcal'], 2),
                'protein_g' => round((float) $data['protein_g'], 2),
                'fat_g' => round((float) $data['fat_g'], 2),
                'carb_g' => round((float) $data['carb_g'], 2),
            ],
        );
    }
}
