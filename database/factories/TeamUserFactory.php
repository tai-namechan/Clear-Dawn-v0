<?php

namespace Database\Factories;

use App\Models\TeamUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeamUser> */
class TeamUserFactory extends Factory
{
    protected $model = TeamUser::class;

    public function definition(): array
    {
        return ['google_subject' => fake()->unique()->uuid(), 'email' => fake()->unique()->safeEmail(), 'name' => fake()->name(), 'status' => 'active'];
    }
}
