<?php

namespace Database\Factories;

use App\Enums\RuleDefinitionKind;
use App\Models\RuleDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuleDefinition>
 */
class RuleDefinitionFactory extends Factory
{
    protected $model = RuleDefinition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'key' => fake()->unique()->slug(2),
            'kind' => RuleDefinitionKind::EvidenceRule,
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'params' => null,
            'evidence' => fake()->optional()->sentence(),
            'population' => fake()->optional()->sentence(),
            'limitations' => fake()->optional()->sentence(),
            'confidence' => fake()->optional()->randomFloat(2, 0, 1),
            'verified_by' => fake()->optional()->name(),
            'version_number' => 1,
            'is_active' => true,
            'is_hard_gate' => false,
        ];
    }
}
