<?php

namespace Database\Factories;

use App\Enums\ProgressionMode;
use App\Enums\RequiredLevel;
use App\Models\ProgramDayStep;
use App\Models\ProgramStepItem;
use App\Models\RoutineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramStepItem>
 */
class ProgramStepItemFactory extends Factory
{
    protected $model = ProgramStepItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_day_step_id' => ProgramDayStep::factory(),
            'routine_item_id' => RoutineItem::factory(),
            'sort_order' => 1,
            'sets' => 3,
            'reps' => 10,
            'rpe_target' => 7.5,
            'required_level' => RequiredLevel::Required,
            'progression_mode' => ProgressionMode::Fixed,
        ];
    }
}
