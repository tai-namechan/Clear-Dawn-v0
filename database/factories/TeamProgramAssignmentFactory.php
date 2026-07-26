<?php

namespace Database\Factories;

use App\Models\TeamProgram;
use App\Models\TeamProgramAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeamProgramAssignment> */
class TeamProgramAssignmentFactory extends Factory
{
    protected $model = TeamProgramAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_program_id' => TeamProgram::factory(),
            'user_id' => User::factory(),
            'status' => 'assigned',
            'assigned_at' => now(),
        ];
    }
}
