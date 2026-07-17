<?php

namespace Database\Factories;

use App\Models\SymptomObservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SymptomObservation>
 */
class SymptomObservationFactory extends Factory
{
    protected $model = SymptomObservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'observed_on' => fake()->date(),
            'body_region' => fake()->randomElement(['left_elbow', 'right_shoulder', 'lower_back']),
            'symptom_kind' => fake()->randomElement(['neural_ulnar', 'pain', 'numbness']),
            'severity' => fake()->numberBetween(0, 10),
            'is_new' => false,
            'note' => fake()->optional()->sentence(),
        ];
    }
}
