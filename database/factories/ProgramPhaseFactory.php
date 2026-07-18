<?php

namespace Database\Factories;

use App\Enums\PhaseIntent;
use App\Models\ProgramPhase;
use App\Models\ProgramVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramPhase>
 */
class ProgramPhaseFactory extends Factory
{
    protected $model = ProgramPhase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_version_id' => ProgramVersion::factory(),
            'name' => '基礎',
            'intent' => PhaseIntent::Base,
            'sort_order' => 1,
        ];
    }
}
