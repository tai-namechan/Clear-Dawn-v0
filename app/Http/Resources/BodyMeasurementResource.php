<?php

namespace App\Http\Resources;

use App\Enums\BodySegment;
use App\Models\BodyMeasurement;
use App\Models\BodyMeasurementSegment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 体組成測定の Inertia 表示用整形。
 *
 * @mixin BodyMeasurement
 */
class BodyMeasurementResource extends JsonResource
{
    /**
     * 体組成測定の表示配列。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $segmentsByKey = $this->segments
            ->keyBy(fn (BodyMeasurementSegment $segment): string => $segment->segment_key->value);

        $segments = [];

        foreach (BodySegment::cases() as $segment) {
            /** @var BodyMeasurementSegment|null $row */
            $row = $segmentsByKey->get($segment->value);

            $segments[$segment->value] = [
                'lean_mass_kg' => $this->nullableFloat($row?->lean_mass_kg),
                'fat_mass_kg' => $this->nullableFloat($row?->fat_mass_kg),
            ];
        }

        return [
            'id' => $this->id,
            'parse_status' => $this->parse_status->value,
            'measured_on' => $this->measured_on->toDateString(),
            'measured_at' => $this->measured_at?->toDateTimeString(),
            'height_cm' => $this->nullableFloat($this->height_cm),
            'age' => $this->nullableInt($this->age),
            'gender' => $this->gender,
            'weight_kg' => $this->nullableFloat($this->weight_kg),
            'lean_body_mass_kg' => $this->nullableFloat($this->lean_body_mass_kg),
            'skeletal_muscle_mass_kg' => $this->nullableFloat($this->skeletal_muscle_mass_kg),
            'protein_kg' => $this->nullableFloat($this->protein_kg),
            'mineral_kg' => $this->nullableFloat($this->mineral_kg),
            'total_body_water_kg' => $this->nullableFloat($this->total_body_water_kg),
            'body_fat_mass_kg' => $this->nullableFloat($this->body_fat_mass_kg),
            'subcutaneous_fat_mass_kg' => $this->nullableFloat($this->subcutaneous_fat_mass_kg),
            'visceral_fat_mass_kg' => $this->nullableFloat($this->visceral_fat_mass_kg),
            'visceral_fat_area_cm2' => $this->nullableFloat($this->visceral_fat_area_cm2),
            'visceral_fat_level' => $this->nullableInt($this->visceral_fat_level),
            'body_fat_percentage' => $this->nullableFloat($this->body_fat_percentage),
            'bmr_kcal' => $this->nullableInt($this->bmr_kcal),
            'tee_kcal' => $this->nullableInt($this->tee_kcal),
            'bio_age' => $this->nullableInt($this->bio_age),
            'bwi_score' => $this->nullableFloat($this->bwi_score),
            'abdominal_circumference_cm' => $this->nullableFloat($this->abdominal_circumference_cm),
            'waist_to_hip_ratio' => $this->nullableFloat($this->waist_to_hip_ratio),
            'recommended_calories_min' => $this->nullableInt($this->recommended_calories_min),
            'recommended_calories_max' => $this->nullableInt($this->recommended_calories_max),
            'recommended_protein_min_g' => $this->nullableFloat($this->recommended_protein_min_g),
            'recommended_protein_max_g' => $this->nullableFloat($this->recommended_protein_max_g),
            'recommended_carbohydrate_min_g' => $this->nullableFloat($this->recommended_carbohydrate_min_g),
            'recommended_carbohydrate_max_g' => $this->nullableFloat($this->recommended_carbohydrate_max_g),
            'recommended_fat_min_g' => $this->nullableFloat($this->recommended_fat_min_g),
            'recommended_fat_max_g' => $this->nullableFloat($this->recommended_fat_max_g),
            'segments' => $segments,
        ];
    }

    /**
     * 空文字を null にした小数。
     */
    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * 空文字を null にした整数。
     */
    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
