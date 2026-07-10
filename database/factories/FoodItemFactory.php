<?php

namespace Database\Factories;

use App\Models\FoodItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoodItem>
 */
class FoodItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'serving_label' => fake()->randomElement(['1杯', '1個', '1皿', '1本']),
            'kcal' => fake()->randomFloat(2, 50, 600),
            'protein_g' => fake()->randomFloat(2, 1, 40),
            'fat_g' => fake()->randomFloat(2, 1, 30),
            'carb_g' => fake()->randomFloat(2, 1, 80),
        ];
    }
}
