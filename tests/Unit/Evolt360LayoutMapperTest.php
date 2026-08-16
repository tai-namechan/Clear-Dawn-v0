<?php

namespace Tests\Unit;

use App\Services\BodyComposition\EvoltBodyCompositionParser;
use App\Services\BodyComposition\ExtractedPdfDocument;
use App\Services\BodyComposition\ExtractedPdfTextItem;
use Tests\TestCase;

class Evolt360LayoutMapperTest extends TestCase
{
    public function test_maps_evolt_result_grid_without_using_section_numbers(): void
    {
        $document = new ExtractedPdfDocument(
            [
                new ExtractedPdfTextItem('16-08-2026 03:07', 21, 776.54),
                new ExtractedPdfTextItem('173 cm', 21, 739.54),
                new ExtractedPdfTextItem('92.3 kg', 164, 739.54),
                new ExtractedPdfTextItem('26', 307, 739.54),
                new ExtractedPdfTextItem('Male', 450, 739.54),
                new ExtractedPdfTextItem('1.', 15.7, 685.1),
                new ExtractedPdfTextItem('3.', 15.7, 604.8),
                new ExtractedPdfTextItem('PROTEIN', 22.5, 604.8),
                new ExtractedPdfTextItem('70.3 / High', 21, 660.54),
                new ExtractedPdfTextItem('22.0 / High', 164, 660.54),
                new ExtractedPdfTextItem('8 / Balanced', 307, 660.54),
                new ExtractedPdfTextItem('39.0 / High', 21, 620.54),
                new ExtractedPdfTextItem('19.1', 164, 620.54),
                new ExtractedPdfTextItem('1888 kCal', 307, 620.54),
                new ExtractedPdfTextItem('14.4 / High', 21, 580.54),
                new ExtractedPdfTextItem('2.9', 164, 580.54),
                new ExtractedPdfTextItem('2907 kCal', 307, 580.54),
                new ExtractedPdfTextItem('5.3 / High', 21, 540.54),
                new ExtractedPdfTextItem('75 / Optimal', 164, 540.54),
                new ExtractedPdfTextItem('50.6 / High', 21, 500.54),
                new ExtractedPdfTextItem('23.8% / High', 164, 500.54),
                new ExtractedPdfTextItem('LEFT ARM', 18.3, 401.3),
                new ExtractedPdfTextItem('3.92 / High', 23, 378.21),
                new ExtractedPdfTextItem('1.36 / High', 138, 378.21),
                new ExtractedPdfTextItem('RIGHT ARM', 347.1, 401.3),
                new ExtractedPdfTextItem('3.79 / High', 355, 378.21),
                new ExtractedPdfTextItem('1.48 / High', 469, 378.21),
                new ExtractedPdfTextItem('TORSO', 229.8, 352.8),
                new ExtractedPdfTextItem('29.78 / High', 23, 330.21),
                new ExtractedPdfTextItem('12.71 / High', 138, 330.21),
                new ExtractedPdfTextItem('95.7 cm', 355, 330.21),
                new ExtractedPdfTextItem('0.82 / Optimal', 469, 330.21),
                new ExtractedPdfTextItem('LEFT LEG', 18.3, 305.7),
                new ExtractedPdfTextItem('10.55 / High', 23, 284.21),
                new ExtractedPdfTextItem('3.16 / High', 138, 284.21),
                new ExtractedPdfTextItem('RIGHT LEG', 347.1, 305.7),
                new ExtractedPdfTextItem('10.73 / High', 355, 284.21),
                new ExtractedPdfTextItem('3.29 / High', 469, 284.21),
            ],
            'anonymized evolt grid',
        );

        $parsed = (new EvoltBodyCompositionParser)->parseDocument($document);

        $this->assertSame(173.0, $parsed->heightCm);
        $this->assertSame(92.3, $parsed->weightKg);
        $this->assertSame(26, $parsed->age);
        $this->assertSame('male', $parsed->gender);
        $this->assertSame(70.3, $parsed->extractedLeanBodyMassKg);
        $this->assertSame(22.0, $parsed->bodyFatMassKg);
        $this->assertSame(8, $parsed->visceralFatLevel);
        $this->assertSame(39.0, $parsed->skeletalMuscleMassKg);
        $this->assertSame(19.1, $parsed->subcutaneousFatMassKg);
        $this->assertSame(1888, $parsed->bmrKcal);
        $this->assertSame(14.4, $parsed->proteinKg);
        $this->assertSame(2.9, $parsed->visceralFatMassKg);
        $this->assertSame(2907, $parsed->teeKcal);
        $this->assertSame(5.3, $parsed->mineralKg);
        $this->assertSame(75.0, $parsed->visceralFatAreaCm2);
        $this->assertSame(50.6, $parsed->totalBodyWaterKg);
        $this->assertSame(23.8, $parsed->bodyFatPercentage);
        $this->assertNotSame(50.6, $parsed->bodyFatPercentage);
        $this->assertNotSame(3.0, $parsed->skeletalMuscleMassKg);
        $this->assertSame(3.92, $parsed->segments['left_arm']['lean_mass_kg']);
        $this->assertSame(1.36, $parsed->segments['left_arm']['fat_mass_kg']);
        $this->assertSame(3.79, $parsed->segments['right_arm']['lean_mass_kg']);
        $this->assertSame(1.48, $parsed->segments['right_arm']['fat_mass_kg']);
        $this->assertSame(29.78, $parsed->segments['torso']['lean_mass_kg']);
        $this->assertSame(12.71, $parsed->segments['torso']['fat_mass_kg']);
        $this->assertSame(10.55, $parsed->segments['left_leg']['lean_mass_kg']);
        $this->assertSame(3.16, $parsed->segments['left_leg']['fat_mass_kg']);
        $this->assertSame(10.73, $parsed->segments['right_leg']['lean_mass_kg']);
        $this->assertSame(3.29, $parsed->segments['right_leg']['fat_mass_kg']);
        $this->assertSame(95.7, $parsed->abdominalCircumferenceCm);
        $this->assertSame(0.82, $parsed->waistToHipRatio);
    }
}
