<?php

namespace Database\Factories;

use App\Models\Recommendation;
use App\Models\RecommendationOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationOption>
 */
class RecommendationOptionFactory extends Factory
{
    protected $model = RecommendationOption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recommendation_id' => Recommendation::factory(),
            'action_key' => fake()->slug(2),
            'label' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'sort_order' => 0,
        ];
    }
}
