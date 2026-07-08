<?php

namespace Database\Factories;

use App\Enums\RoutineItemCategory;
use App\Enums\TrackingType;
use App\Models\LifeArea;
use App\Models\RoutineItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutineItem>
 */
class RoutineItemFactory extends Factory
{
    protected $model = RoutineItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'life_area_id' => null,
            'name' => fake()->words(2, true),
            'category' => fake()->randomElement(RoutineItemCategory::cases()),
            'tracking_type' => fake()->randomElement(TrackingType::cases()),
            'default_load_unit' => null,
            'default_amount_unit' => null,
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
