<?php

namespace Database\Factories;

use App\Enums\StepPurpose;
use App\Models\Routine;
use App\Models\RoutineItem;
use App\Models\RoutineStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutineStep>
 */
class RoutineStepFactory extends Factory
{
    protected $model = RoutineStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'routine_id' => Routine::factory(),
            'routine_item_id' => RoutineItem::factory(),
            'video_id' => null,
            'purpose' => fake()->optional()->randomElement(StepPurpose::cases()),
            'sort_order' => fake()->numberBetween(1, 10),
            'target_load' => fake()->optional()->randomFloat(2, 5, 100),
            'load_unit' => 'kg',
            'target_amount' => fake()->optional()->numberBetween(5, 15),
            'amount_unit' => 'reps',
            'target_blocks' => fake()->optional()->numberBetween(1, 5),
            'rest_seconds' => fake()->optional()->numberBetween(30, 120),
            'note' => fake()->optional()->sentence(),
        ];
    }

    public function forRoutine(Routine $routine): static
    {
        return $this->state(fn (array $attributes) => [
            'routine_id' => $routine->id,
            'routine_item_id' => RoutineItem::factory()->create(['user_id' => $routine->user_id])->id,
        ]);
    }
}
