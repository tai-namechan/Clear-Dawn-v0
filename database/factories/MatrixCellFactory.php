<?php

namespace Database\Factories;

use App\Enums\MatrixRowKey;
use App\Models\LifeArea;
use App\Models\MatrixCell;
use App\Models\MatrixRow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatrixCell>
 */
class MatrixCellFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * matrix_rows はグローバルマスタのため、Factory では作成せず
     * seed 済みの行（未 seed ならこの Factory 内で firstOrCreate した行）を参照する。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'life_area_id' => LifeArea::factory(),
            'matrix_row_id' => fn () => $this->resolveRow(MatrixRowKey::Current)->id,
        ];
    }

    /**
     * Reference the row for the given key.
     */
    public function forRow(MatrixRowKey $key): static
    {
        return $this->state(fn (array $attributes) => [
            'matrix_row_id' => $this->resolveRow($key)->id,
        ]);
    }

    private function resolveRow(MatrixRowKey $key): MatrixRow
    {
        return MatrixRow::query()->firstOrCreate(
            ['key' => $key->value],
            [
                'label' => $key->label(),
                'sort_order' => $key->sortOrder(),
                'is_checkable' => $key->isCheckable(),
            ],
        );
    }
}
