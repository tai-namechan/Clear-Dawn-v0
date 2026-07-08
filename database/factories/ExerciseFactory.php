<?php

namespace Database\Factories;

use App\Enums\ExerciseCategory;
use App\Enums\TrackingType;
use App\Models\Exercise;
use App\Models\LifeArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'life_area_id' => null,
            'name' => fake()->words(2, true),
            'category' => fake()->randomElement(ExerciseCategory::cases()),
            'tracking_type' => fake()->randomElement(TrackingType::cases()),
            'note' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function forLifeArea(LifeArea $lifeArea): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $lifeArea->user_id,
            'life_area_id' => $lifeArea->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
