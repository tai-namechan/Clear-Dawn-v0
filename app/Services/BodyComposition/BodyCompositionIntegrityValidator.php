<?php

namespace App\Services\BodyComposition;

/**
 * 体組成主要項目の品質ゲート。
 */
class BodyCompositionIntegrityValidator
{
    /**
     * 主要項目の欠損と項目間の矛盾を判定する。
     */
    public function evaluate(ParsedBodyComposition $parsed): BodyCompositionIntegrity
    {
        $failures = [];
        $weight = $parsed->weightKg;
        $lean = $parsed->resolvedLeanBodyMassKg();
        $skeletal = $parsed->skeletalMuscleMassKg;
        $fatPercent = $parsed->bodyFatPercentage;
        $fatMass = $parsed->bodyFatMassKg;
        $water = $parsed->totalBodyWaterKg;

        if ($weight === null || $weight <= 0) {
            $failures[] = 'weight';
        }

        if ($skeletal === null || $skeletal <= 0) {
            $failures[] = 'skeletal_muscle';
        }

        if ($fatPercent === null || $fatPercent < 0 || $fatPercent > 100) {
            $failures[] = 'body_fat_percentage';
        }

        $hasCore = $failures === [];

        if ($lean !== null && $weight !== null && $lean > $weight) {
            $failures[] = 'lean_exceeds_weight';
        }

        if ($fatMass !== null && $weight !== null && $fatMass > $weight) {
            $failures[] = 'fat_exceeds_weight';
        }

        if ($lean !== null && $fatMass !== null && $weight !== null) {
            $sum = $lean + $fatMass;

            if (abs($sum - $weight) > 1.5) {
                $failures[] = 'composition_sum';
            }
        }

        if ($fatPercent !== null && $water !== null && abs($fatPercent - $water) < 0.05) {
            $failures[] = 'fat_percent_matches_water';
        }

        if ($skeletal !== null && $skeletal < 5) {
            $failures[] = 'skeletal_muscle_too_small';
        }

        return new BodyCompositionIntegrity(
            hasCoreMeasurements: $hasCore,
            isConsistent: $hasCore && ! in_array('fat_percent_matches_water', $failures, true)
                && ! in_array('skeletal_muscle_too_small', $failures, true)
                && ! in_array('lean_exceeds_weight', $failures, true)
                && ! in_array('fat_exceeds_weight', $failures, true)
                && ! in_array('composition_sum', $failures, true),
            failures: array_values($failures),
        );
    }
}
