<?php

namespace Database\Factories;

use App\Models\MatrixCell;
use App\Models\MatrixCellItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatrixCellItem>
 */
class MatrixCellItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matrix_cell_id' => MatrixCell::factory(),
            'title' => fake()->sentence(3),
            'memo' => null,
            'is_completed' => false,
            'completed_at' => null,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }

    /**
     * Indicate that the item is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }
}
