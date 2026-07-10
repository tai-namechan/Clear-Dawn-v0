<?php

namespace Database\Factories;

use App\Models\NutritionGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NutritionGoal>
 */
class NutritionGoalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kcal' => 2200,
            'protein_g' => 120,
            'fat_g' => 70,
            'carb_g' => 250,
        ];
    }
}
