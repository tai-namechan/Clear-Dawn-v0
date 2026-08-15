<?php

namespace Tests\Unit;

use App\Services\BodyComposition\EvoltBodyCompositionParser;
use App\Services\BodyComposition\PdfTextExtractor;
use Tests\Support\EvoltSamplePdf;
use Tests\TestCase;

class EvoltBodyCompositionParserTest extends TestCase
{
    public function test_parses_evolt_english_labels_without_filling_zeros(): void
    {
        $parsed = (new EvoltBodyCompositionParser)->parse(<<<'TEXT'
            Weight 92.3 kg
            Skeletal Muscle 39.0 kg
            PBF 23.8 %
            Lean Body Mass 70.3 kg
            Abdominal Circumference 95.7 cm
            Left Arm Lean 3.92 Fat 1.36
            Right Arm Lean 3.79 Fat 1.48
            Torso Lean 29.78 Fat 12.71
            Left Leg Lean 10.55 Fat 3.16
            Right Leg Lean 10.73 Fat 3.29
            TEXT);

        $this->assertSame(92.3, $parsed->weightKg);
        $this->assertSame(70.3, $parsed->extractedLeanBodyMassKg);
        $this->assertSame(39.0, $parsed->skeletalMuscleMassKg);
        $this->assertSame(23.8, $parsed->bodyFatPercentage);
        $this->assertSame(95.7, $parsed->abdominalCircumferenceCm);
        $this->assertSame(3.92, $parsed->segments['left_arm']['lean_mass_kg']);
        $this->assertSame(1.36, $parsed->segments['left_arm']['fat_mass_kg']);
        $this->assertSame(3.79, $parsed->segments['right_arm']['lean_mass_kg']);
        $this->assertSame(29.78, $parsed->segments['torso']['lean_mass_kg']);
        $this->assertSame(10.55, $parsed->segments['left_leg']['lean_mass_kg']);
        $this->assertSame(10.73, $parsed->segments['right_leg']['lean_mass_kg']);
        $this->assertTrue($parsed->hasPersistableValues());
    }

    public function test_computes_lean_body_mass_from_weight_and_body_fat(): void
    {
        $parsed = (new EvoltBodyCompositionParser)->parse("Weight 92.3\nPBF 23.8");

        $this->assertNull($parsed->extractedLeanBodyMassKg);
        $this->assertSame(70.33, $parsed->resolvedLeanBodyMassKg());
    }

    public function test_missing_fields_stay_null_instead_of_zero(): void
    {
        $parsed = (new EvoltBodyCompositionParser)->parse('Weight 80.0 kg');

        $this->assertSame(80.0, $parsed->weightKg);
        $this->assertNull($parsed->skeletalMuscleMassKg);
        $this->assertNull($parsed->bodyFatPercentage);
        $this->assertNull($parsed->abdominalCircumferenceCm);
        $this->assertNull($parsed->segments['left_arm']['lean_mass_kg']);
        $this->assertNull($parsed->segments['left_arm']['fat_mass_kg']);
        $this->assertNull($parsed->resolvedLeanBodyMassKg());
    }

    public function test_parses_japanese_labels(): void
    {
        $parsed = (new EvoltBodyCompositionParser)->parse(<<<'TEXT'
            体重 88.1
            骨格筋量 37.2
            体脂肪率 21.4
            除脂肪体重 69.2
            腹囲 90.5
            左腕 Lean 3.10 Fat 1.10
            右腕 Lean 3.20 Fat 1.20
            胴体 Lean 28.00 Fat 11.00
            左脚 Lean 10.00 Fat 3.00
            右脚 Lean 10.10 Fat 3.10
            TEXT);

        $this->assertSame(88.1, $parsed->weightKg);
        $this->assertSame(37.2, $parsed->skeletalMuscleMassKg);
        $this->assertSame(21.4, $parsed->bodyFatPercentage);
        $this->assertSame(69.2, $parsed->extractedLeanBodyMassKg);
        $this->assertSame(90.5, $parsed->abdominalCircumferenceCm);
        $this->assertSame(3.10, $parsed->segments['left_arm']['lean_mass_kg']);
        $this->assertSame(28.00, $parsed->segments['torso']['lean_mass_kg']);
    }

    public function test_sample_pdf_text_is_extractable_without_external_tools(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'evolt-extract-');
        $this->assertNotFalse($path);
        $pdfPath = $path.'.pdf';
        rename($path, $pdfPath);
        file_put_contents($pdfPath, EvoltSamplePdf::content());

        $text = (new PdfTextExtractor)->extract($pdfPath);
        $parsed = (new EvoltBodyCompositionParser)->parse($text);

        $this->assertSame(92.3, $parsed->weightKg);
        $this->assertSame(3.92, $parsed->segments['left_arm']['lean_mass_kg']);

        unlink($pdfPath);
    }
}
