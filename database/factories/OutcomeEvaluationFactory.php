<?php

namespace Database\Factories;

use App\Models\OutcomeEvaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutcomeEvaluation>
 */
class OutcomeEvaluationFactory extends Factory
{
    protected $model = OutcomeEvaluation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'recommendation_decision_id' => null,
            'routine_session_id' => null,
            'evaluated_on' => fake()->date(),
            'outcome_key' => fake()->slug(2),
            'score' => fake()->optional()->randomFloat(2, 0, 10),
            'note' => fake()->optional()->sentence(),
            'metrics_snapshot' => null,
        ];
    }
}
