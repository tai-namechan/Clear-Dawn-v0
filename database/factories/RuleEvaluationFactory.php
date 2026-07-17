<?php

namespace Database\Factories;

use App\Models\RuleDefinition;
use App\Models\RuleEvaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuleEvaluation>
 */
class RuleEvaluationFactory extends Factory
{
    protected $model = RuleEvaluation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'rule_definition_id' => RuleDefinition::factory(),
            'evaluated_on' => fake()->date(),
            'triggered' => fake()->boolean(),
            'inputs_snapshot' => null,
            'outputs_snapshot' => null,
        ];
    }
}
