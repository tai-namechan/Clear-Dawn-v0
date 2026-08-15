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
        $this->assertNull($parsed->bmrKcal);
        $this->assertNull($parsed->teeKcal);
        $this->assertTrue($parsed->hasPersistableValues());
    }

    public function test_parses_full_evolt_fields_without_storing_name(): void
    {
        $parsed = (new EvoltBodyCompositionParser)->parse(<<<'TEXT'
            Name Taro
            Measured At 2026-08-16 03:07
            Height 178.0 cm
            Age 34
            Gender Male
            Weight 92.3 kg
            Lean Body Mass 70.3 kg
            Skeletal Muscle 39.0 kg
            Protein Mass 14.8 kg
            Mineral Mass 4.2 kg
            Total Body Water 48.6 kg
            Body Fat Mass 22.0 kg
            Subcutaneous Fat 18.1 kg
            Visceral Fat Mass 3.9 kg
            Visceral Fat Area 112.0
            Visceral Fat Level 12
            PBF 23.8 %
            BMR 1980
            TEE 2650
            Bio Age 31
            BWI Score 72.0
            Abdominal Circumference 95.7 cm
            Waist to Hip 0.91
            Recommended Calories 2200-2600
            Recommended Protein 140-180
            Recommended Carbohydrate 220-280
            Recommended Fat 55-75
            TEXT);

        $this->assertSame('2026-08-16 03:07:00', $parsed->measuredAt);
        $this->assertSame(178.0, $parsed->heightCm);
        $this->assertSame(34, $parsed->age);
        $this->assertSame('male', $parsed->gender);
        $this->assertSame(14.8, $parsed->proteinKg);
        $this->assertSame(4.2, $parsed->mineralKg);
        $this->assertSame(48.6, $parsed->totalBodyWaterKg);
        $this->assertSame(22.0, $parsed->bodyFatMassKg);
        $this->assertSame(18.1, $parsed->subcutaneousFatMassKg);
        $this->assertSame(3.9, $parsed->visceralFatMassKg);
        $this->assertSame(112.0, $parsed->visceralFatAreaCm2);
        $this->assertSame(12, $parsed->visceralFatLevel);
        $this->assertSame(1980, $parsed->bmrKcal);
        $this->assertSame(2650, $parsed->teeKcal);
        $this->assertSame(31, $parsed->bioAge);
        $this->assertSame(72.0, $parsed->bwiScore);
        $this->assertSame(0.91, $parsed->waistToHipRatio);
        $this->assertSame(2200, $parsed->recommendedCaloriesMin);
        $this->assertSame(2600, $parsed->recommendedCaloriesMax);
        $this->assertSame(140.0, $parsed->recommendedProteinMinG);
        $this->assertSame(180.0, $parsed->recommendedProteinMaxG);
        $this->assertSame(220.0, $parsed->recommendedCarbohydrateMinG);
        $this->assertSame(280.0, $parsed->recommendedCarbohydrateMaxG);
        $this->assertSame(55.0, $parsed->recommendedFatMinG);
        $this->assertSame(75.0, $parsed->recommendedFatMaxG);
        $this->assertArrayNotHasKey('name', $parsed->toPersistenceAttributes());
        $this->assertStringNotContainsString('Taro', $parsed->raw['text']);
        $this->assertDoesNotMatchRegularExpression('/^Name\\b/m', $parsed->raw['text']);
    }

    public function test_parses_japanese_full_fields_without_confusing_bio_age(): void
    {
        $parsed = (new EvoltBodyCompositionParser)->parse(<<<'TEXT'
            氏名 太郎
            測定日時 2026-08-16 03:07
            身長 178.0
            年齢 34
            性別 男性
            体重 92.3
            除脂肪体重 70.3
            骨格筋量 39.0
            タンパク質量 14.8
            ミネラル量 4.2
            体水分量 48.6
            体脂肪量 22.0
            皮下脂肪量 18.1
            内臓脂肪量 3.9
            内臓脂肪面積 112.0
            内臓脂肪レベル 12
            体脂肪率 23.8
            基礎代謝 1980
            総消費エネルギー 2650
            体年齢 31
            腹囲 95.7
            ウエスト対ヒップ 0.91
            推奨カロリー 2200-2600
            推奨タンパク質 140-180
            推奨炭水化物 220-280
            推奨脂質 55-75
            TEXT);

        $this->assertSame(34, $parsed->age);
        $this->assertSame(31, $parsed->bioAge);
        $this->assertSame('male', $parsed->gender);
        $this->assertSame(22.0, $parsed->bodyFatMassKg);
        $this->assertSame(3.9, $parsed->visceralFatMassKg);
        $this->assertSame(1980, $parsed->bmrKcal);
        $this->assertSame(2650, $parsed->teeKcal);
        $this->assertSame(2200, $parsed->recommendedCaloriesMin);
        $this->assertStringNotContainsString('太郎', $parsed->raw['text']);
        $this->assertStringNotContainsString('氏名', $parsed->raw['text']);
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
        $this->assertNull($parsed->bmrKcal);
        $this->assertNull($parsed->visceralFatMassKg);
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
