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
     * @return array{
     *     id: string,
     *     measured_on: string,
     *     weight_kg: float|null,
     *     lean_body_mass_kg: float|null,
     *     skeletal_muscle_mass_kg: float|null,
     *     body_fat_percentage: float|null,
     *     abdominal_circumference_cm: float|null,
     *     segments: array<string, array{lean_mass_kg: float|null, fat_mass_kg: float|null}>
     * }
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
            'measured_on' => $this->measured_on->toDateString(),
            'weight_kg' => $this->nullableFloat($this->weight_kg),
            'lean_body_mass_kg' => $this->nullableFloat($this->lean_body_mass_kg),
            'skeletal_muscle_mass_kg' => $this->nullableFloat($this->skeletal_muscle_mass_kg),
            'body_fat_percentage' => $this->nullableFloat($this->body_fat_percentage),
            'abdominal_circumference_cm' => $this->nullableFloat($this->abdominal_circumference_cm),
            'segments' => $segments,
        ];
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
