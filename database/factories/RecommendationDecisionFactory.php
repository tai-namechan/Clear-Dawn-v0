<?php

namespace Database\Factories;

use App\Models\Recommendation;
use App\Models\RecommendationDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationDecision>
 */
class RecommendationDecisionFactory extends Factory
{
    protected $model = RecommendationDecision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'recommendation_id' => Recommendation::factory(),
            'recommendation_option_id' => null,
            'action_key' => fake()->slug(2),
            'reason' => fake()->optional()->sentence(),
            'result_snapshot' => null,
        ];
    }
}
