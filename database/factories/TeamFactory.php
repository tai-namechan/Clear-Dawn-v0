<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Team> */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return ['name' => fake()->company(), 'slug' => fake()->unique()->slug(), 'organization_type' => 'team', 'status' => 'active', 'timezone' => 'Asia/Tokyo', 'created_by_team_user_id' => TeamUser::factory()];
    }
}
