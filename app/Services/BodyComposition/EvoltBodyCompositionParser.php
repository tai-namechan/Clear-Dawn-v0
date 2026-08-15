<?php

namespace App\Services\BodyComposition;

use App\Enums\BodySegment;

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

        return new ParsedBodyComposition(
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
            bodyFatPercentage: $this->firstFloat($normalized, [
                '/\\bPBF\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/Percent(?:age)?\\s*Body\\s*Fat\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/Body\\s*Fat(?:\\s*%|\\s*Percentage)?\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/体脂肪率\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            abdominalCircumferenceCm: $this->firstFloat($normalized, [
                '/Abdominal\\s*Circumference\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/\\bWaist\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/i',
                '/腹囲\\s*[:=]?\\s*(\\d+(?:\\.\\d+)?)/u',
            ]),
            segments: $segments,
            raw: ['text' => $normalized],
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

    private function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\\t ]+/', ' ', $text) ?? $text;

        return trim($text);
    }
}
