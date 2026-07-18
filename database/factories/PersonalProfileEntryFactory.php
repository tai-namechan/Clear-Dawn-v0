<?php

namespace Database\Factories;

use App\Models\PersonalProfileEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalProfileEntry>
 */
class PersonalProfileEntryFactory extends Factory
{
    protected $model = PersonalProfileEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key' => PersonalProfileEntry::KEY_ONE_RM_BENCH,
            'value_numeric' => fake()->randomFloat(1, 40, 120),
            'unit' => 'kg',
            'effective_from' => '2026-07-16',
            'source' => 'test',
        ];
    }
}
