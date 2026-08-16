<?php

namespace App\Services\BodyComposition;

use App\Enums\BodySegment;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * EVOLT 360 系PDFテキストの体組成値解析。
 *
 * 帳票グリッドを優先し、取れない項目だけラベル照合する。
 * 項目番号や参考範囲は測定値にしない。
 */
class EvoltBodyCompositionParser
{
    public function __construct(
        private Evolt360LayoutMapper $layoutMapper = new Evolt360LayoutMapper,
    ) {}

    /**
     * 抽出テキストを体組成DTOへ変換する。
     */
    public function parse(string $text): ParsedBodyComposition
    {
        return $this->parseDocument(ExtractedPdfDocument::fromPlainText($text));
    }

    /**
     * 位置付き抽出結果を体組成DTOへ変換する。
     */
    public function parseDocument(ExtractedPdfDocument $document): ParsedBodyComposition
    {
        $layout = $this->layoutMapper->map($document);
        $labels = $this->parseLabels($document->readingOrderText);
        $merged = $this->preferNonNull($layout, $labels);

        /** @var array<string, array{lean_mass_kg: float|null, fat_mass_kg: float|null}> $segments */
        $segments = $merged['segments'] ?? [];

        foreach (BodySegment::cases() as $segment) {
            $segments[$segment->value] ??= $this->parseSegment($document->readingOrderText, $segment);
        }

        return new ParsedBodyComposition(
            measuredAt: $merged['measuredAt'] ?? null,
            heightCm: $merged['heightCm'] ?? null,
            age: $merged['age'] ?? null,
            gender: $merged['gender'] ?? null,
            weightKg: $merged['weightKg'] ?? null,
            extractedLeanBodyMassKg: $merged['extractedLeanBodyMassKg'] ?? null,
            skeletalMuscleMassKg: $merged['skeletalMuscleMassKg'] ?? null,
            proteinKg: $merged['proteinKg'] ?? null,
            mineralKg: $merged['mineralKg'] ?? null,
            totalBodyWaterKg: $merged['totalBodyWaterKg'] ?? null,
            bodyFatMassKg: $merged['bodyFatMassKg'] ?? null,
            subcutaneousFatMassKg: $merged['subcutaneousFatMassKg'] ?? null,
            visceralFatMassKg: $merged['visceralFatMassKg'] ?? null,
            visceralFatAreaCm2: $merged['visceralFatAreaCm2'] ?? null,
            visceralFatLevel: $merged['visceralFatLevel'] ?? null,
            bodyFatPercentage: $merged['bodyFatPercentage'] ?? null,
            bmrKcal: $merged['bmrKcal'] ?? null,
            teeKcal: $merged['teeKcal'] ?? null,
            bioAge: $merged['bioAge'] ?? null,
            bwiScore: $merged['bwiScore'] ?? null,
            abdominalCircumferenceCm: $merged['abdominalCircumferenceCm'] ?? null,
            waistToHipRatio: $merged['waistToHipRatio'] ?? null,
            recommendedCaloriesMin: $merged['recommendedCaloriesMin'] ?? null,
            recommendedCaloriesMax: $merged['recommendedCaloriesMax'] ?? null,
            recommendedProteinMinG: $merged['recommendedProteinMinG'] ?? null,
            recommendedProteinMaxG: $merged['recommendedProteinMaxG'] ?? null,
            recommendedCarbohydrateMinG: $merged['recommendedCarbohydrateMinG'] ?? null,
            recommendedCarbohydrateMaxG: $merged['recommendedCarbohydrateMaxG'] ?? null,
            recommendedFatMinG: $merged['recommendedFatMinG'] ?? null,
            recommendedFatMaxG: $merged['recommendedFatMaxG'] ?? null,
            segments: $segments,
            raw: ['text' => $this->redactPii($document->readingOrderText)],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function parseLabels(string $text): array
    {
        $normalized = $this->normalize($text);
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

        $height = $this->firstMeasurement($normalized, [
            '/Height\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)\\s*cm/i',
            '/身長\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
        ]);

        if ($height !== null && ($height < 100 || $height > 250)) {
            $height = null;
        }

        $weight = $this->firstMeasurement($normalized, [
            '/(?<!Lean\\sBody\\s)Weight\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)(?:\\s*kg)?/i',
            '/体重\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)(?:\\s*kg)?/u',
        ]);

        if ($weight !== null && ($weight < 20 || $weight > 400)) {
            $weight = null;
        }

        $segments = [];

        foreach (BodySegment::cases() as $segment) {
            $segments[$segment->value] = $this->parseSegment($normalized, $segment);
        }

        return [
            'measuredAt' => $this->firstDateTime($normalized),
            'heightCm' => $height,
            'age' => $this->firstInt($normalized, [
                '/(?<!Bio\\s)(?<!Biological\\s)Age\\s*[:=]?\\s*(\\d{1,2})(?!\\s*\\.)/i',
                '/(?<!体)年齢\\s*[:=]?\\s*(\\d{1,2})(?!\\s*\\.)/u',
            ]),
            'gender' => $this->firstGender($normalized),
            'weightKg' => $weight,
            'extractedLeanBodyMassKg' => $this->firstMeasurement($normalized, [
                '/Lean\\s*Body\\s*Mass\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/\\bLBM\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/除脂肪体[重量]\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            'skeletalMuscleMassKg' => $this->firstMeasurement($normalized, [
                '/Skeletal\\s*Muscle(?:\\s*Mass)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/骨格筋(?:量)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ], minimum: 5),
            'proteinKg' => $this->firstMeasurement($normalized, [
                '/Protein\\s*Mass\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/(?:タンパク質量|プロテイン)\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ], minimum: 1),
            'mineralKg' => $this->firstMeasurement($normalized, [
                '/Mineral(?:\\s*Mass)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/ミネラル(?:量)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ], minimum: 0.5),
            'totalBodyWaterKg' => $this->firstMeasurement($normalized, [
                '/Total\\s*Body\\s*Water\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/\\bTBW\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/体水分(?:量)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            'bodyFatMassKg' => $this->firstMeasurement($normalized, [
                '/Body\\s*Fat\\s*Mass\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/全身体脂肪量\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
                '/(?<!皮)(?<!臓)体脂肪量\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            'subcutaneousFatMassKg' => $this->firstMeasurement($normalized, [
                '/Subcutaneous\\s*Fat(?:\\s*Mass)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/皮下脂肪(?:量)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            'visceralFatMassKg' => $this->firstMeasurement($normalized, [
                '/Visceral\\s*Fat\\s*Mass\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/内臓脂肪量\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            'visceralFatAreaCm2' => $this->firstMeasurement($normalized, [
                '/Visceral\\s*Fat\\s*Area\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/内臓脂肪面積\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            'visceralFatLevel' => $this->firstInt($normalized, [
                '/Visceral\\s*Fat\\s*Level\\s*[:=]?\\s*(\\d+)/i',
                '/内臓脂肪レベル\\s*[:=]?\\s*(\\d+)/u',
            ]),
            'bodyFatPercentage' => $this->firstMeasurement($normalized, [
                '/\\bPBF\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/Percent(?:age)?\\s*Body\\s*Fat\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/(?:TOTAL\\s*)?BODY\\s*FAT\\s*PERCENTAGE\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)\\s*%/i',
                '/全体脂肪率\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)\\s*%/u',
                '/(?<!全)体脂肪率\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            'bmrKcal' => $this->firstInt($normalized, [
                '/\\bBMR\\s*[:=]?\\s*(\\d{3,5})/i',
                '/Basal\\s*Metabolic\\s*Rate\\s*[:=]?\\s*(\\d{3,5})/i',
                '/基礎代謝(?:量|率)?\\s*[:=]?\\s*(\\d{3,5})/u',
            ]),
            'teeKcal' => $this->firstInt($normalized, [
                '/\\bTEE\\s*[:=]?\\s*(\\d{3,5})/i',
                '/Total\\s*Energy\\s*Expenditure\\s*[:=]?\\s*(\\d{3,5})/i',
                '/総(?:消費)?エネルギー(?:消費量)?\\s*[:=]?\\s*(\\d{3,5})/u',
            ]),
            'bioAge' => $this->firstInt($normalized, [
                '/Bio(?:logical)?\\s*Age\\s*[:=]?\\s*(\\d{1,2})/i',
                '/生体年齢\\s*[:=]?\\s*(\\d{1,2})/u',
                '/体年齢\\s*[:=]?\\s*(\\d{1,2})/u',
                '/生物学的年齢\\s*[:=]?\\s*(\\d{1,2})/u',
            ]),
            'bwiScore' => $this->firstMeasurement($normalized, [
                '/\\bBWI(?:\\s*Score)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
            ]),
            'abdominalCircumferenceCm' => $this->firstMeasurement($normalized, [
                '/Abdominal\\s*Circumference\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/腹囲\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            'waistToHipRatio' => $this->firstMeasurement($normalized, [
                '/Waist(?:-|\\s*)to(?:-|\\s*)Hip(?:\\s*Ratio)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/\\bWHR\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/ウエスト\\s*(?:\\/|対)?\\s*ヒップ(?:比)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            'recommendedCaloriesMin' => $this->intOrNull($recommendedCalories[0]),
            'recommendedCaloriesMax' => $this->intOrNull($recommendedCalories[1]),
            'recommendedProteinMinG' => $recommendedProtein[0],
            'recommendedProteinMaxG' => $recommendedProtein[1],
            'recommendedCarbohydrateMinG' => $recommendedCarbs[0],
            'recommendedCarbohydrateMaxG' => $recommendedCarbs[1],
            'recommendedFatMinG' => $recommendedFat[0],
            'recommendedFatMaxG' => $recommendedFat[1],
            'segments' => $segments,
        ];
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

            $pair = '/'.$label.'[^\\n]{0,120}?(\\d+(?:\\.\\d+)?)\\s*(?:\\/\\s*[A-Za-z]+)?[^\\n]{0,40}?(\\d+(?:\\.\\d+)?)\\s*(?:\\/\\s*[A-Za-z]+)?/iu';

            if (preg_match($pair, $text, $match) === 1) {
                $lean = (float) $match[1];
                $fat = (float) $match[2];

                if ($lean < 80 && $fat < 80 && $lean !== $fat) {
                    return [
                        'lean_mass_kg' => $lean,
                        'fat_mass_kg' => $fat,
                    ];
                }
            }

            $block = '/'.$label.'\\b(.{0,240}?)(?=Left\\s*Arm|Right\\s*Arm|Torso|Trunk|Left\\s*Leg|Right\\s*Leg|左腕|右腕|胴体|体幹|左脚|右脚|Weight|体重|$)/isu';

            if (preg_match($block, $text, $section) === 1) {
                $lean = $this->firstMeasurement($section[1], [
                    '/Lean(?:\\s*Mass)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                    '/除脂肪量\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
                ]);
                $fat = $this->firstMeasurement($section[1], [
                    '/Fat(?:\\s*Mass)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                    '/脂肪質量\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
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
    private function firstMeasurement(string $text, array $patterns, float $minimum = 0): ?float
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match) !== 1) {
                continue;
            }

            $value = (float) $match[1];

            if ($value < $minimum) {
                continue;
            }

            return $value;
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
            '/\\b(\\d{2})-(\\d{2})-(\\d{4})(?:\\s+(\\d{1,2}:\\d{2}(?::\\d{2})?))?\\b/',
        ];

        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $text, $match) !== 1) {
                continue;
            }

            try {
                if ($index === 3) {
                    if (! isset($match[2], $match[3])) {
                        continue;
                    }

                    $time = $match[4] ?? '00:00:00';

                    if (substr_count($time, ':') === 1) {
                        $time .= ':00';
                    }

                    return Carbon::parse($match[3].'-'.$match[2].'-'.$match[1].' '.$time)->toDateTimeString();
                }

                return Carbon::parse($match[1])->toDateTimeString();
            } catch (Throwable) {
                continue;
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
        $redacted = preg_replace('/^(?:Name|氏名|NAME)\\s*[:=]?\\s*.+$/miu', '', $text) ?? $text;
        $redacted = preg_replace('/(\\d{2}-\\d{2}-\\d{4}\\s+\\d{1,2}:\\d{2}(?::\\d{2})?)\\s+[A-Z][A-Za-z\\-]+/u', '$1', $redacted) ?? $redacted;
        $redacted = preg_replace('/\\n{2,}/', "\n", $redacted) ?? $redacted;

        return trim($redacted);
    }

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\\t ]+/', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  array<string, mixed>  $preferred
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function preferNonNull(array $preferred, array $fallback): array
    {
        $merged = $fallback;

        foreach ($preferred as $key => $value) {
            if ($key === 'segments' && is_array($value)) {
                $merged['segments'] = $this->mergeSegments(
                    is_array($fallback['segments'] ?? null) ? $fallback['segments'] : [],
                    $value,
                );

                continue;
            }

            if ($value !== null && $value !== []) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, array{lean_mass_kg: float|null, fat_mass_kg: float|null}>  $fallback
     * @param  array<string, array{lean_mass_kg: float|null, fat_mass_kg: float|null}>  $preferred
     * @return array<string, array{lean_mass_kg: float|null, fat_mass_kg: float|null}>
     */
    private function mergeSegments(array $fallback, array $preferred): array
    {
        $merged = $fallback;

        foreach ($preferred as $key => $segment) {
            $current = $merged[$key] ?? ['lean_mass_kg' => null, 'fat_mass_kg' => null];

            $merged[$key] = [
                'lean_mass_kg' => $segment['lean_mass_kg'] ?? $current['lean_mass_kg'],
                'fat_mass_kg' => $segment['fat_mass_kg'] ?? $current['fat_mass_kg'],
            ];
        }

        return $merged;
    }
}
