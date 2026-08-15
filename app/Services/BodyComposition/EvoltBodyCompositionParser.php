<?php

namespace App\Services\BodyComposition;

use App\Enums\BodySegment;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * EVOLT 360 系PDFテキストの体組成値解析。
 */
class EvoltBodyCompositionParser
{
    /**
     * 抽出テキストを体組成DTOへ変換する。
     */
    public function parse(string $text): ParsedBodyComposition
    {
        $normalized = $this->normalize($text);

        $segments = [];

        foreach (BodySegment::cases() as $segment) {
            $segments[$segment->value] = $this->parseSegment($normalized, $segment);
        }

        $recommendedCalories = $this->firstRange($normalized, [
            '/Recommended\\s*(?:Calorie|Calories|Energy)\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)\\s*[-~～]\\s*(\\d+(?:\\.\\d+)?)/i',
            '/推奨カロリー\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)\\s*[-~～]\\s*(\\d+(?:\\.\\d+)?)/u',
        ]);
        $recommendedProtein = $this->firstRange($normalized, [
            '/Recommended\\s*Protein\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)\\s*[-~～]\\s*(\\d+(?:\\.\\d+)?)/i',
            '/推奨タンパク質\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)\\s*[-~～]\\s*(\\d+(?:\\.\\d+)?)/u',
        ]);
        $recommendedCarbs = $this->firstRange($normalized, [
            '/Recommended\\s*Carbohydrate(?:s)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)\\s*[-~～]\\s*(\\d+(?:\\.\\d+)?)/i',
            '/推奨炭水化物\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)\\s*[-~～]\\s*(\\d+(?:\\.\\d+)?)/u',
        ]);
        $recommendedFat = $this->firstRange($normalized, [
            '/Recommended\\s*Fat\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)\\s*[-~～]\\s*(\\d+(?:\\.\\d+)?)/i',
            '/推奨脂質\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)\\s*[-~～]\\s*(\\d+(?:\\.\\d+)?)/u',
        ]);

        return new ParsedBodyComposition(
            measuredAt: $this->firstDateTime($normalized),
            heightCm: $this->firstFloat($normalized, [
                '/Height\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/身長\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            age: $this->firstInt($normalized, [
                '/(?<!Bio\\s)(?<!Biological\\s)Age\\s*[:=]?\\s*(\\d+)/i',
                '/(?<!体)年齢\\s*[:=]?\\s*(\\d+)/u',
            ]),
            gender: $this->firstGender($normalized),
            weightKg: $this->firstFloat($normalized, [
                '/Weight\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/体重\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            extractedLeanBodyMassKg: $this->firstFloat($normalized, [
                '/Lean\\s*Body\\s*Mass\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/\\bLBM\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/除脂肪体重\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            skeletalMuscleMassKg: $this->firstFloat($normalized, [
                '/Skeletal\\s*Muscle(?:\\s*Mass)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/骨格筋(?:量)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            proteinKg: $this->firstFloat($normalized, [
                '/Protein\\s*Mass\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/タンパク質量\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            mineralKg: $this->firstFloat($normalized, [
                '/Mineral(?:\\s*Mass)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/ミネラル(?:量)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            totalBodyWaterKg: $this->firstFloat($normalized, [
                '/Total\\s*Body\\s*Water\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/\\bTBW\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/体水分(?:量)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            bodyFatMassKg: $this->firstFloat($normalized, [
                '/Body\\s*Fat\\s*Mass\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/全身体脂肪量\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
                '/(?:^|\\n)体脂肪量\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            subcutaneousFatMassKg: $this->firstFloat($normalized, [
                '/Subcutaneous\\s*Fat(?:\\s*Mass)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/皮下脂肪(?:量)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            visceralFatMassKg: $this->firstFloat($normalized, [
                '/Visceral\\s*Fat\\s*Mass\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/内臓脂肪量\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            visceralFatAreaCm2: $this->firstFloat($normalized, [
                '/Visceral\\s*Fat\\s*Area\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/内臓脂肪面積\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            visceralFatLevel: $this->firstInt($normalized, [
                '/Visceral\\s*Fat\\s*Level\\s*[:=]?\\s*(\\d+)/i',
                '/内臓脂肪レベル\\s*[:=]?\\s*(\\d+)/u',
            ]),
            bodyFatPercentage: $this->firstFloat($normalized, [
                '/\\bPBF\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/Percent(?:age)?\\s*Body\\s*Fat\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/Body\\s*Fat(?:\\s*%|\\s*Percentage)\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/体脂肪率\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            bmrKcal: $this->firstInt($normalized, [
                '/\\bBMR\\s*[:=]?\\s*(\\d+)/i',
                '/Basal\\s*Metabolic\\s*Rate\\s*[:=]?\\s*(\\d+)/i',
                '/基礎代謝(?:量)?\\s*[:=]?\\s*(\\d+)/u',
            ]),
            teeKcal: $this->firstInt($normalized, [
                '/\\bTEE\\s*[:=]?\\s*(\\d+)/i',
                '/Total\\s*Energy\\s*Expenditure\\s*[:=]?\\s*(\\d+)/i',
                '/総消費エネルギー\\s*[:=]?\\s*(\\d+)/u',
            ]),
            bioAge: $this->firstInt($normalized, [
                '/Bio(?:logical)?\\s*Age\\s*[:=]?\\s*(\\d+)/i',
                '/生体年齢\\s*[:=]?\\s*(\\d+)/u',
                '/体年齢\\s*[:=]?\\s*(\\d+)/u',
            ]),
            bwiScore: $this->firstFloat($normalized, [
                '/\\bBWI(?:\\s*Score)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
            ]),
            abdominalCircumferenceCm: $this->firstFloat($normalized, [
                '/Abdominal\\s*Circumference\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/腹囲\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            waistToHipRatio: $this->firstFloat($normalized, [
                '/Waist(?:-|\\s*)to(?:-|\\s*)Hip(?:\\s*Ratio)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/\\bWHR\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/ウエスト\\s*(?:\\/|対)?\\s*ヒップ(?:比)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            recommendedCaloriesMin: $this->intOrNull($recommendedCalories[0]),
            recommendedCaloriesMax: $this->intOrNull($recommendedCalories[1]),
            recommendedProteinMinG: $recommendedProtein[0],
            recommendedProteinMaxG: $recommendedProtein[1],
            recommendedCarbohydrateMinG: $recommendedCarbs[0],
            recommendedCarbohydrateMaxG: $recommendedCarbs[1],
            recommendedFatMinG: $recommendedFat[0],
            recommendedFatMaxG: $recommendedFat[1],
            segments: $segments,
            raw: ['text' => $this->redactPii($normalized)],
        );
    }

    /**
     * @return array{lean_mass_kg: float|null, fat_mass_kg: float|null}
     */
    private function parseSegment(string $text, BodySegment $segment): array
    {
        $labels = match ($segment) {
            BodySegment::LeftArm => ['Left\\s*Arm', '左腕'],
            BodySegment::RightArm => ['Right\\s*Arm', '右腕'],
            BodySegment::Torso => ['Torso', 'Trunk', '胴体', '体幹'],
            BodySegment::LeftLeg => ['Left\\s*Leg', '左脚', '左足'],
            BodySegment::RightLeg => ['Right\\s*Leg', '右脚', '右足'],
        };

        foreach ($labels as $label) {
            $inline = '/'.$label.'[^\\n]{0,80}?Lean(?:\\s*Mass)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)[^\\n]{0,40}?Fat(?:\\s*Mass)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/iu';

            if (preg_match($inline, $text, $match) === 1) {
                return [
                    'lean_mass_kg' => (float) $match[1],
                    'fat_mass_kg' => (float) $match[2],
                ];
            }

            $block = '/'.$label.'\\b(.{0,240}?)(?=Left\\s*Arm|Right\\s*Arm|Torso|Trunk|Left\\s*Leg|Right\\s*Leg|左腕|右腕|胴体|体幹|左脚|右脚|Weight|体重|$)/isu';

            if (preg_match($block, $text, $section) === 1) {
                $lean = $this->firstFloat($section[1], [
                    '/Lean(?:\\s*Mass)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                    '/除脂肪量\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
                ]);
                $fat = $this->firstFloat($section[1], [
                    '/Fat(?:\\s*Mass)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                    '/体脂肪量\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
                ]);

                if ($lean !== null || $fat !== null) {
                    return [
                        'lean_mass_kg' => $lean,
                        'fat_mass_kg' => $fat,
                    ];
                }
            }
        }

        return [
            'lean_mass_kg' => null,
            'fat_mass_kg' => null,
        ];
    }

    /**
     * @param  list<string>  $patterns
     */
    private function firstFloat(string $text, array $patterns): ?float
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                return (float) $match[1];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function firstInt(string $text, array $patterns): ?int
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                return (int) $match[1];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $patterns
     * @return array{0: float|null, 1: float|null}
     */
    private function firstRange(string $text, array $patterns): array
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                return [(float) $match[1], (float) $match[2]];
            }
        }

        return [null, null];
    }

    private function firstDateTime(string $text): ?string
    {
        $patterns = [
            '/Measured(?:\\s*At)?\\s*[:=]?\\s*(\\d{4}[-\\/]\\d{1,2}[-\\/]\\d{1,2}(?:[ T]\\d{1,2}:\\d{2}(?::\\d{2})?)?)/i',
            '/Test\\s*Date\\s*[:=]?\\s*(\\d{4}[-\\/]\\d{1,2}[-\\/]\\d{1,2}(?:[ T]\\d{1,2}:\\d{2}(?::\\d{2})?)?)/i',
            '/測定日時\\s*[:=]?\\s*(\\d{4}[-\\/]\\d{1,2}[-\\/]\\d{1,2}(?:[ T]\\d{1,2}:\\d{2}(?::\\d{2})?)?)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match) !== 1) {
                continue;
            }

            try {
                return Carbon::parse($match[1])->toDateTimeString();
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function firstGender(string $text): ?string
    {
        if (preg_match('/(?:Gender|Sex|性別)\\s*[:=]?\\s*([A-Za-z\\x{3040}-\\x{30FF}\\x{4E00}-\\x{9FFF}]+)/u', $text, $match) !== 1) {
            return null;
        }

        $value = mb_strtolower($match[1]);

        return match (true) {
            in_array($value, ['male', 'm', '男', '男性'], true) => 'male',
            in_array($value, ['female', 'f', '女', '女性'], true) => 'female',
            default => null,
        };
    }

    private function intOrNull(?float $value): ?int
    {
        if ($value === null) {
            return null;
        }

        return (int) round($value);
    }

    /**
     * 氏名行の除去。
     *
     * 解析には使わず、保存用テキストから個人情報だけ落とす。
     */
    private function redactPii(string $text): string
    {
        $redacted = preg_replace('/^(?:Name|氏名)\\s*[:=]?\\s*.+$/miu', '', $text) ?? $text;
        $redacted = preg_replace('/\\n{2,}/', "\n", $redacted) ?? $redacted;

        return trim($redacted);
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\\t ]+/', ' ', $text) ?? $text;

        return trim($text);
    }
}
