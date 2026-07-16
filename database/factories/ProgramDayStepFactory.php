<?php

namespace Database\Factories;

use App\Enums\ProgramStepKind;
use App\Enums\RequiredLevel;
use App\Models\ProgramDayStep;
use App\Models\ProgramDayTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramDayStep>
 */
class ProgramDayStepFactory extends Factory
{
    protected $model = ProgramDayStep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_day_template_id' => ProgramDayTemplate::factory(),
            'program_choice_option_id' => null,
            'name' => fake()->words(2, true),
            'step_kind' => ProgramStepKind::Strength,
            'sort_order' => 1,
            'required_level' => RequiredLevel::Required,
        ];
    }
}
