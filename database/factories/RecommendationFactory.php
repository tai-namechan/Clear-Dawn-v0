<?php

namespace Database\Factories;

use App\Enums\RecommendationScope;
use App\Enums\RecommendationStatus;
use App\Models\Recommendation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recommendation>
 */
class RecommendationFactory extends Factory
{
    protected $model = Recommendation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'rule_evaluation_id' => null,
            'recommended_on' => fake()->date(),
            'scope' => RecommendationScope::A,
            'title' => fake()->sentence(3),
            'rationale' => fake()->optional()->sentence(),
            'goal_impact' => fake()->optional()->sentence(),
            'plan_diff' => null,
            'confidence' => fake()->optional()->randomFloat(2, 0, 1),
            'missing_data' => null,
            'is_interrupt' => false,
            'status' => RecommendationStatus::Pending,
        ];
    }
}
