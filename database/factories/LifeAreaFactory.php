<?php

namespace Database\Factories;

use App\Enums\LifeAreaColor;
use App\Models\LifeArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LifeArea>
 */
class LifeAreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->word(),
            'color' => fake()->randomElement(LifeAreaColor::cases()),
            'sort_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the life area is hidden from the matrix.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
