<?php

namespace Database\Factories;

use App\Models\ProgramChoiceGroup;
use App\Models\ProgramDayTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramChoiceGroup>
 */
class ProgramChoiceGroupFactory extends Factory
{
    protected $model = ProgramChoiceGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_day_template_id' => ProgramDayTemplate::factory(),
            'name' => '選択メニュー',
            'selection_hint' => fake()->optional()->sentence(),
        ];
    }
}
