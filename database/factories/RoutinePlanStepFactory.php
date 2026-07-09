<?php

namespace Database\Factories;

use App\Enums\StepPurpose;
use App\Models\RoutineItem;
use App\Models\RoutinePlan;
use App\Models\RoutinePlanStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoutinePlanStep>
 */
class RoutinePlanStepFactory extends Factory
{
    protected $model = RoutinePlanStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'routine_plan_id' => RoutinePlan::factory(),
            'routine_item_id' => RoutineItem::factory(),
            'title' => null,
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

    public function forPlan(RoutinePlan $plan): static
    {
        return $this->state(fn (array $attributes) => [
            'routine_plan_id' => $plan->id,
            'routine_item_id' => RoutineItem::factory()->create(['user_id' => $plan->user_id])->id,
        ]);
    }
}
