<?php

namespace Database\Factories;

use App\Enums\BodySegment;
use App\Models\BodyMeasurement;
use App\Models\BodyMeasurementSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BodyMeasurementSegment>
 */
class BodyMeasurementSegmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body_measurement_id' => BodyMeasurement::factory(),
            'segment_key' => BodySegment::LeftArm,
            'lean_mass_kg' => 3.92,
            'fat_mass_kg' => 1.36,
        ];
    }
}
