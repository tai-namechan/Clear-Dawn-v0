<?php

namespace App\Services\BodyComposition;

/**
 * PDFから読み取った体組成値。
 *
 * 欠損は null。0 で埋めない。氏名は個人情報のため持たない。
 */
final readonly class ParsedBodyComposition
{
    /**
     * @param  array<string, array{lean_mass_kg: float|null, fat_mass_kg: float|null}>  $segments
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $measuredAt,
        public ?float $heightCm,
        public ?int $age,
        public ?string $gender,
        public ?float $weightKg,
        public ?float $extractedLeanBodyMassKg,
        public ?float $skeletalMuscleMassKg,
        public ?float $proteinKg,
        public ?float $mineralKg,
        public ?float $totalBodyWaterKg,
        public ?float $bodyFatMassKg,
        public ?float $subcutaneousFatMassKg,
        public ?float $visceralFatMassKg,
        public ?float $visceralFatAreaCm2,
        public ?int $visceralFatLevel,
        public ?float $bodyFatPercentage,
        public ?int $bmrKcal,
        public ?int $teeKcal,
        public ?int $bioAge,
        public ?float $bwiScore,
        public ?float $abdominalCircumferenceCm,
        public ?float $waistToHipRatio,
        public ?int $recommendedCaloriesMin,
        public ?int $recommendedCaloriesMax,
        public ?float $recommendedProteinMinG,
        public ?float $recommendedProteinMaxG,
        public ?float $recommendedCarbohydrateMinG,
        public ?float $recommendedCarbohydrateMaxG,
        public ?float $recommendedFatMinG,
        public ?float $recommendedFatMaxG,
        public array $segments,
        public array $raw,
    ) {}

    /**
     * 保存できる値が1つでもあるか。
     */
    public function hasPersistableValues(): bool
    {
        foreach ($this->toPersistenceAttributes() as $value) {
            if ($value !== null) {
                return true;
            }
        }

        foreach ($this->segments as $segment) {
            if ($segment['lean_mass_kg'] !== null || $segment['fat_mass_kg'] !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * 徐脂肪体重。PDF値を優先し、無ければ体重と体脂肪率から算出する。
     */
    public function resolvedLeanBodyMassKg(): ?float
    {
        if ($this->extractedLeanBodyMassKg !== null) {
            return $this->extractedLeanBodyMassKg;
        }

        if ($this->weightKg === null || $this->bodyFatPercentage === null) {
            return null;
        }

        return round($this->weightKg * (1 - ($this->bodyFatPercentage / 100)), 2);
    }

    /**
     * 測定レコードへ保存する属性。
     *
     * @return array<string, mixed>
     */
    public function toPersistenceAttributes(): array
    {
        return [
            'measured_at' => $this->measuredAt,
            'height_cm' => $this->heightCm,
            'age' => $this->age,
            'gender' => $this->gender,
            'weight_kg' => $this->weightKg,
            'lean_body_mass_kg' => $this->resolvedLeanBodyMassKg(),
            'skeletal_muscle_mass_kg' => $this->skeletalMuscleMassKg,
            'protein_kg' => $this->proteinKg,
            'mineral_kg' => $this->mineralKg,
            'total_body_water_kg' => $this->totalBodyWaterKg,
            'body_fat_mass_kg' => $this->bodyFatMassKg,
            'subcutaneous_fat_mass_kg' => $this->subcutaneousFatMassKg,
            'visceral_fat_mass_kg' => $this->visceralFatMassKg,
            'visceral_fat_area_cm2' => $this->visceralFatAreaCm2,
            'visceral_fat_level' => $this->visceralFatLevel,
            'body_fat_percentage' => $this->bodyFatPercentage,
            'bmr_kcal' => $this->bmrKcal,
            'tee_kcal' => $this->teeKcal,
            'bio_age' => $this->bioAge,
            'bwi_score' => $this->bwiScore,
            'abdominal_circumference_cm' => $this->abdominalCircumferenceCm,
            'waist_to_hip_ratio' => $this->waistToHipRatio,
            'recommended_calories_min' => $this->recommendedCaloriesMin,
            'recommended_calories_max' => $this->recommendedCaloriesMax,
            'recommended_protein_min_g' => $this->recommendedProteinMinG,
            'recommended_protein_max_g' => $this->recommendedProteinMaxG,
            'recommended_carbohydrate_min_g' => $this->recommendedCarbohydrateMinG,
            'recommended_carbohydrate_max_g' => $this->recommendedCarbohydrateMaxG,
            'recommended_fat_min_g' => $this->recommendedFatMinG,
            'recommended_fat_max_g' => $this->recommendedFatMaxG,
        ];
    }
}
