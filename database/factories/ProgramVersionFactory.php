<?php

namespace Database\Factories;

use App\Enums\ProgramVersionStatus;
use App\Models\Program;
use App\Models\ProgramVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramVersion>
 */
class ProgramVersionFactory extends Factory
{
    protected $model = ProgramVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'version_number' => 1,
            'status' => ProgramVersionStatus::Active,
            'starts_on' => '2026-07-16',
            'ends_on' => '2026-10-01',
            'approved_at' => now(),
        ];
    }
}
