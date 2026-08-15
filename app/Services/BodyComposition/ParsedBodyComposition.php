<?php

namespace App\Services\BodyComposition;

/**
 * PDFから読み取った体組成値。
 *
 * 欠損は null。0 で埋めない。
 */
final readonly class ParsedBodyComposition
{
    /**
     * @param  array<string, array{lean_mass_kg: float|null, fat_mass_kg: float|null}>  $segments
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?float $weightKg,
        public ?float $extractedLeanBodyMassKg,
        public ?float $skeletalMuscleMassKg,
        public ?float $bodyFatPercentage,
        public ?float $abdominalCircumferenceCm,
        public array $segments,
        public array $raw,
    ) {}

    /**
     * 保存できる数値が1つでもあるか。
     */
    public function hasPersistableValues(): bool
    {
        if ($this->weightKg !== null
            || $this->extractedLeanBodyMassKg !== null
            || $this->skeletalMuscleMassKg !== null
            || $this->bodyFatPercentage !== null
            || $this->abdominalCircumferenceCm !== null
        ) {
            return true;
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
}
