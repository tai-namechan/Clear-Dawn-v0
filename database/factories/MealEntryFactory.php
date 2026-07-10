<?php

namespace Database\Factories;

use App\Enums\MealType;
use App\Models\MealEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MealEntry>
 */
class MealEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'food_item_id' => null,
            'eaten_on' => fake()->date(),
            'meal_type' => fake()->randomElement(MealType::cases()),
            'name' => fake()->words(2, true),
            'quantity' => fake()->randomFloat(2, 0.5, 3),
            'kcal' => fake()->randomFloat(2, 50, 800),
            'protein_g' => fake()->randomFloat(2, 1, 50),
            'fat_g' => fake()->randomFloat(2, 1, 40),
            'carb_g' => fake()->randomFloat(2, 1, 100),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
