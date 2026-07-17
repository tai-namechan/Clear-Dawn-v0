<?php

namespace Database\Factories;

use App\Models\ProgramStepItem;
use App\Models\ProgramWeek;
use App\Models\ProgramWeekItemPrescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramWeekItemPrescription>
 */
class ProgramWeekItemPrescriptionFactory extends Factory
{
    protected $model = ProgramWeekItemPrescription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_week_id' => ProgramWeek::factory(),
            'program_step_item_id' => ProgramStepItem::factory(),
            'percent_of_reference' => 0.7456,
            'sets' => 4,
            'reps' => 5,
            'rpe_target' => 7.0,
            'is_test' => false,
        ];
    }
}
