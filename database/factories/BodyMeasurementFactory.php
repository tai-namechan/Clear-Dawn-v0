<?php

namespace Database\Factories;

use App\Enums\BodyMeasurementSource;
use App\Enums\BodyMeasurementStatus;
use App\Enums\BodySegment;
use App\Models\BodyMeasurement;
use App\Models\BodyMeasurementSegment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BodyMeasurement>
 */
class BodyMeasurementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'measured_on' => fake()->date(),
            'input_source' => BodyMeasurementSource::Pdf,
            'source_pdf_path' => null,
            'parse_status' => BodyMeasurementStatus::Confirmed,
            'confirmed_at' => now(),
            'weight_kg' => 92.3,
            'lean_body_mass_kg' => 70.33,
            'skeletal_muscle_mass_kg' => 39.0,
            'body_fat_percentage' => 23.8,
            'abdominal_circumference_cm' => 95.7,
            'raw_extracted_json' => null,
        ];
    }

    /**
     * 仕様サンプルの部位別値を付ける。
     */
    public function withSampleSegments(): static
    {
        return $this->afterCreating(function (BodyMeasurement $measurement): void {
            $samples = [
                BodySegment::LeftArm->value => ['lean_mass_kg' => 3.92, 'fat_mass_kg' => 1.36],
                BodySegment::RightArm->value => ['lean_mass_kg' => 3.79, 'fat_mass_kg' => 1.48],
                BodySegment::Torso->value => ['lean_mass_kg' => 29.78, 'fat_mass_kg' => 12.71],
                BodySegment::LeftLeg->value => ['lean_mass_kg' => 10.55, 'fat_mass_kg' => 3.16],
                BodySegment::RightLeg->value => ['lean_mass_kg' => 10.73, 'fat_mass_kg' => 3.29],
            ];

            foreach ($samples as $key => $values) {
                BodyMeasurementSegment::factory()->create([
                    'body_measurement_id' => $measurement->id,
                    'segment_key' => $key,
                    'lean_mass_kg' => $values['lean_mass_kg'],
                    'fat_mass_kg' => $values['fat_mass_kg'],
                ]);
            }
        });
    }
}
