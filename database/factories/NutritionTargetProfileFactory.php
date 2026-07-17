<?php

namespace Database\Factories;

use App\Models\NutritionTargetProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NutritionTargetProfile>
 */
class NutritionTargetProfileFactory extends Factory
{
    protected $model = NutritionTargetProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'program_version_id' => null,
            'program_phase_id' => null,
            'name' => fake()->words(2, true),
            'starts_on' => fake()->date(),
            'ends_on' => fake()->optional()->date(),
            'kcal' => 2200,
            'protein_g' => 120,
            'fat_g' => 70,
            'carb_g' => 250,
            'note' => fake()->optional()->sentence(),
        ];
    }
}
