<?php

namespace Tests\Unit;

use App\Services\BodyComposition\BodyCompositionIntegrityValidator;
use App\Services\BodyComposition\EvoltBodyCompositionParser;
use Tests\TestCase;

class BodyCompositionIntegrityValidatorTest extends TestCase
{
    public function test_rejects_missing_weight_and_section_number_muscle(): void
    {
        $parsed = (new EvoltBodyCompositionParser)->parse(<<<'TEXT'
            Skeletal Muscle 3.0 kg
            PBF 50.6 %
            TEXT);

        $integrity = (new BodyCompositionIntegrityValidator)->evaluate($parsed);

        $this->assertFalse($integrity->hasCoreMeasurements);
        $this->assertFalse($integrity->canConfirm());
        $this->assertContains('weight', $integrity->failures);
    }

    public function test_rejects_body_fat_percentage_copied_from_water(): void
    {
        $parsed = (new EvoltBodyCompositionParser)->parse(<<<'TEXT'
            Weight 92.3 kg
            Skeletal Muscle 39.0 kg
            Lean Body Mass 70.3 kg
            Body Fat Mass 22.0 kg
            PBF 50.6 %
            Total Body Water 50.6 kg
            TEXT);

        $integrity = (new BodyCompositionIntegrityValidator)->evaluate($parsed);

        $this->assertTrue($integrity->hasCoreMeasurements);
        $this->assertFalse($integrity->isConsistent);
        $this->assertContains('fat_percent_matches_water', $integrity->failures);
    }

    public function test_accepts_coherent_evolt_core_values(): void
    {
        $parsed = (new EvoltBodyCompositionParser)->parse(<<<'TEXT'
            Weight 92.3 kg
            Skeletal Muscle 39.0 kg
            Lean Body Mass 70.3 kg
            Body Fat Mass 22.0 kg
            PBF 23.8 %
            Total Body Water 50.6 kg
            TEXT);

        $integrity = (new BodyCompositionIntegrityValidator)->evaluate($parsed);

        $this->assertTrue($integrity->canConfirm());
        $this->assertSame([], $integrity->failures);
    }
}
