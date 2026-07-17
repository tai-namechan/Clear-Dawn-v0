<?php

namespace Database\Factories;

use App\Models\ProgramConstraint;
use App\Models\ProgramVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramConstraint>
 */
class ProgramConstraintFactory extends Factory
{
    protected $model = ProgramConstraint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_version_id' => ProgramVersion::factory(),
            'key' => 'constraint_'.fake()->unique()->numberBetween(1, 100000),
            'kind' => 'program_rule',
            'description' => fake()->sentence(),
            'params' => null,
            'sort_order' => 0,
        ];
    }
}
