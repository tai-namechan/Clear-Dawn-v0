<?php

namespace App\Services\BodyComposition;

/**
 * EVOLT 360 帳票グリッドからの値割り当て。
 *
 * 項目番号の直後の数字は使わず、結果カードの行・列と単位で意味を決める。
 */
class Evolt360LayoutMapper
{
    /**
     * 位置付きテキストから帳票構造の値を読む。
     *
     * @return array<string, mixed>
     */
    public function map(ExtractedPdfDocument $document): array
    {
        $values = $this->emptyValues();

        if ($document->items === []) {
            return $values;
        }

        $header = $this->headerRow($document->items);
        $values = [...$values, ...$header];

        $grid = $this->resultGrid($document->items, $header['header_y'] ?? null);

        if ($grid !== null) {
            $values = [...$values, ...$grid];
        }

        $values = [...$values, ...$this->segmentValues($document->items)];
        $values = [...$values, ...$this->sideScores($document->items)];
        $values = [...$values, ...$this->nutritionRanges($document->readingOrderText)];

        unset($values['header_y']);

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyValues(): array
    {
        return [
            'measuredAt' => null,
            'heightCm' => null,
            'age' => null,
            'gender' => null,
            'weightKg' => null,
            'extractedLeanBodyMassKg' => null,
            'skeletalMuscleMassKg' => null,
            'proteinKg' => null,
            'mineralKg' => null,
            'totalBodyWaterKg' => null,
            'bodyFatMassKg' => null,
            'subcutaneousFatMassKg' => null,
            'visceralFatMassKg' => null,
            'visceralFatAreaCm2' => null,
            'visceralFatLevel' => null,
            'bodyFatPercentage' => null,
            'bmrKcal' => null,
            'teeKcal' => null,
            'bioAge' => null,
            'bwiScore' => null,
            'abdominalCircumferenceCm' => null,
            'waistToHipRatio' => null,
            'recommendedCaloriesMin' => null,
            'recommendedCaloriesMax' => null,
            'recommendedProteinMinG' => null,
            'recommendedProteinMaxG' => null,
            'recommendedCarbohydrateMinG' => null,
            'recommendedCarbohydrateMaxG' => null,
            'recommendedFatMinG' => null,
            'recommendedFatMaxG' => null,
            'segments' => [],
        ];
    }

    /**
     * @param  list<ExtractedPdfTextItem>  $items
     * @return array<string, mixed>
     */
    private function headerRow(array $items): array
    {
        $heights = [];

        foreach ($items as $item) {
            if (preg_match('/^(\d+(?:\.\d+)?)\s*cm$/i', trim($item->text), $match) !== 1) {
                continue;
            }

            $height = (float) $match[1];

            if ($height < 100 || $height > 250) {
                continue;
            }

            $heights[] = [$item, $height];
        }

        foreach ($heights as [$heightItem, $height]) {
            $weight = null;
            $age = null;
            $gender = null;

            foreach ($items as $item) {
                if (abs($item->y - $heightItem->y) > 12) {
                    continue;
                }

                if (preg_match('/^(\d+(?:\.\d+)?)\s*kg$/i', trim($item->text), $match) === 1) {
                    $candidate = (float) $match[1];

                    if ($candidate >= 20 && $candidate <= 400) {
                        $weight = $candidate;
                    }
                }

                if (preg_match('/^(\d{1,2})$/', trim($item->text), $match) === 1) {
                    $candidate = (int) $match[1];

                    if ($candidate >= 10 && $candidate <= 90) {
                        $age = $candidate;
                    }
                }

                $gender = $this->normalizeGender(trim($item->text)) ?? $gender;
            }

            if ($weight !== null) {
                return [
                    'header_y' => $heightItem->y,
                    'heightCm' => $height,
                    'weightKg' => $weight,
                    'age' => $age,
                    'gender' => $gender,
                    'measuredAt' => $this->nearbyDate($items, $heightItem->y),
                ];
            }
        }

        return [];
    }

    /**
     * @param  list<ExtractedPdfTextItem>  $items
     */
    private function nearbyDate(array $items, float $headerY): ?string
    {
        foreach ($items as $item) {
            if ($item->y < $headerY || $item->y > $headerY + 50) {
                continue;
            }

            if (preg_match('/\b(\d{2})-(\d{2})-(\d{4})(?:\s+(\d{1,2}:\d{2}(?::\d{2})?))?\b/', $item->text, $match) === 1) {
                $time = $match[4] ?? '00:00:00';

                if (substr_count($time, ':') === 1) {
                    $time .= ':00';
                }

                return $match[3].'-'.$match[2].'-'.$match[1].' '.$time;
            }
        }

        return null;
    }

    /**
     * @param  list<ExtractedPdfTextItem>  $items
     * @return array<string, mixed>|null
     */
    private function resultGrid(array $items, ?float $headerY): ?array
    {
        $minY = $headerY !== null ? $headerY - 270 : 470.0;
        $maxY = $headerY !== null ? $headerY - 50 : 690.0;
        $cells = [];

        foreach ($items as $item) {
            if ($item->y < $minY || $item->y > $maxY || $item->x > 380) {
                continue;
            }

            $measurement = $this->measurement(trim($item->text));

            if ($measurement === null) {
                continue;
            }

            $cells[] = ['item' => $item, ...$measurement];
        }

        $rows = $this->clusterByY($cells, 10.0);

        if (count($rows) < 4) {
            return null;
        }

        $rows = array_slice($rows, 0, 5);
        $mapped = [];

        $assign = [
            0 => ['extractedLeanBodyMassKg', 'bodyFatMassKg', 'visceralFatLevel'],
            1 => ['skeletalMuscleMassKg', 'subcutaneousFatMassKg', 'bmrKcal'],
            2 => ['proteinKg', 'visceralFatMassKg', 'teeKcal'],
            3 => ['mineralKg', 'visceralFatAreaCm2'],
            4 => ['totalBodyWaterKg', 'bodyFatPercentage'],
        ];

        foreach ($assign as $rowIndex => $keys) {
            $row = $rows[$rowIndex] ?? [];
            usort($row, fn (array $a, array $b): int => $a['item']->x <=> $b['item']->x);

            foreach ($keys as $col => $key) {
                $cell = $row[$col] ?? null;

                if ($cell === null) {
                    continue;
                }

                $mapped[$key] = $this->typedValue($key, $cell);
            }
        }

        if (! isset($mapped['extractedLeanBodyMassKg'], $mapped['totalBodyWaterKg'], $mapped['bodyFatPercentage'])) {
            return null;
        }

        if ($mapped['bodyFatPercentage'] === $mapped['totalBodyWaterKg']) {
            return null;
        }

        return $mapped;
    }

    /**
     * @param  list<ExtractedPdfTextItem>  $items
     * @return array<string, mixed>
     */
    private function segmentValues(array $items): array
    {
        $cells = [];

        foreach ($items as $item) {
            if ($item->y > 410 || $item->y < 250) {
                continue;
            }

            $measurement = $this->measurement(trim($item->text));

            if ($measurement === null) {
                continue;
            }

            $cells[] = ['item' => $item, ...$measurement];
        }

        $rows = $this->clusterByY($cells, 10.0);

        if (count($rows) < 3) {
            return [
                'segments' => [],
                'abdominalCircumferenceCm' => null,
                'waistToHipRatio' => null,
            ];
        }

        $armRow = $this->sortedRow($rows[0]);
        $torsoRow = $this->sortedRow($rows[1]);
        $legRow = $this->sortedRow($rows[2]);

        $leftArm = $this->pairOnSide($armRow, false);
        $rightArm = $this->pairOnSide($armRow, true);
        $torso = $this->pairOnSide($torsoRow, false);
        $leftLeg = $this->pairOnSide($legRow, false);
        $rightLeg = $this->pairOnSide($legRow, true);

        $waist = null;
        $whr = null;

        foreach ($torsoRow as $cell) {
            if ($cell['unit'] === 'cm' && $cell['value'] >= 50 && $cell['value'] <= 200) {
                $waist = $cell['value'];
            }

            if ($cell['item']->x >= 400 && $cell['unit'] === null && $cell['value'] > 0.5 && $cell['value'] < 1.5) {
                $whr = $cell['value'];
            }
        }

        return [
            'segments' => [
                'left_arm' => $leftArm,
                'right_arm' => $rightArm,
                'torso' => $torso,
                'left_leg' => $leftLeg,
                'right_leg' => $rightLeg,
            ],
            'abdominalCircumferenceCm' => $waist,
            'waistToHipRatio' => $whr,
        ];
    }

    /**
     * @param  list<array{item: ExtractedPdfTextItem, value: float, unit: string|null}>  $row
     * @return list<array{item: ExtractedPdfTextItem, value: float, unit: string|null}>
     */
    private function sortedRow(array $row): array
    {
        usort($row, fn (array $a, array $b): int => $a['item']->x <=> $b['item']->x);

        return $row;
    }

    /**
     * @param  list<array{item: ExtractedPdfTextItem, value: float, unit: string|null}>  $row
     * @return array{lean_mass_kg: float|null, fat_mass_kg: float|null}
     */
    private function pairOnSide(array $row, bool $rightSide): array
    {
        $side = array_values(array_filter(
            $row,
            function (array $cell) use ($rightSide): bool {
                if (in_array($cell['unit'], ['cm', 'kcal'], true)) {
                    return false;
                }

                return $rightSide ? $cell['item']->x >= 300 : $cell['item']->x < 300;
            },
        ));

        return [
            'lean_mass_kg' => $side[0]['value'] ?? null,
            'fat_mass_kg' => $side[1]['value'] ?? null,
        ];
    }

    /**
     * @param  list<ExtractedPdfTextItem>  $items
     * @param  list<string>  $labels
     */
    private function findLabel(array $items, array $labels): ?ExtractedPdfTextItem
    {
        foreach ($items as $item) {
            foreach ($labels as $label) {
                if (mb_stripos($item->text, $label) !== false) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<ExtractedPdfTextItem>  $items
     * @return array<string, mixed>
     */
    private function sideScores(array $items): array
    {
        $bioAge = null;
        $bwi = null;
        $bioLabel = $this->findLabel($items, ['BIO AGE', '生物学的年齢', '体年齢']);
        $bwiLabel = $this->findLabel($items, ['BWI', '生物学的健康指数']);

        foreach ($items as $item) {
            $text = trim($item->text);

            if ($bioLabel !== null && abs($item->x - $bioLabel->x) < 80 && $item->y < $bioLabel->y && $item->y > $bioLabel->y - 80) {
                if (preg_match('/^(\d{1,2})$/', $text, $match) === 1) {
                    $bioAge = (int) $match[1];
                }
            }

            if ($bwiLabel !== null && abs($item->x - $bwiLabel->x) < 80 && $item->y < $bwiLabel->y && $item->y > $bwiLabel->y - 80) {
                if (preg_match('/^(\d+(?:\.\d+)?)$/', $text, $match) === 1) {
                    $candidate = (float) $match[1];

                    if ($candidate > 0 && $candidate <= 15) {
                        $bwi = $candidate;
                    }
                }
            }
        }

        return [
            'bioAge' => $bioAge,
            'bwiScore' => $bwi,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function nutritionRanges(string $text): array
    {
        $values = [];

        if (preg_match('/\b(\d{3,5})\s*[-~～]\s*(\d{3,5})\b/u', $text, $match) === 1) {
            $min = (int) $match[1];
            $max = (int) $match[2];

            if ($min >= 800 && $max <= 8000 && $max >= $min) {
                $values['recommendedCaloriesMin'] = $min;
                $values['recommendedCaloriesMax'] = $max;
            }
        }

        if (preg_match_all('/(\d+(?:\.\d+)?)\s*g\s*[-~～]\s*(\d+(?:\.\d+)?)\s*g/i', $text, $matches, PREG_SET_ORDER) !== false) {
            $macros = [];

            foreach ($matches as $match) {
                $macros[] = [(float) $match[1], (float) $match[2]];
            }

            if (isset($macros[0])) {
                $values['recommendedProteinMinG'] = $macros[0][0];
                $values['recommendedProteinMaxG'] = $macros[0][1];
            }

            if (isset($macros[1])) {
                $values['recommendedCarbohydrateMinG'] = $macros[1][0];
                $values['recommendedCarbohydrateMaxG'] = $macros[1][1];
            }

            if (isset($macros[2])) {
                $values['recommendedFatMinG'] = $macros[2][0];
                $values['recommendedFatMaxG'] = $macros[2][1];
            }
        }

        return $values;
    }

    /**
     * @return array{value: float, unit: string|null}|null
     */
    private function measurement(string $text): ?array
    {
        if ($text === '' || str_starts_with($text, '[') || preg_match('/^\d+\.$/', $text) === 1) {
            return null;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*(kg|kcal|cm2|cm|%)(?:\s*\/\s*[A-Za-z]+)?$/i', $text, $match) === 1) {
            return [
                'value' => (float) $match[1],
                'unit' => strtolower($match[2]),
            ];
        }

        if (preg_match('/^(\d+(?:\.\d+)?)(?:\s*\/\s*[A-Za-z]+)?$/', $text, $match) !== 1) {
            return null;
        }

        return [
            'value' => (float) $match[1],
            'unit' => null,
        ];
    }

    /**
     * @param  array{value: float, unit: string|null}  $cell
     */
    private function typedValue(string $key, array $cell): int|float
    {
        if (in_array($key, ['visceralFatLevel', 'bmrKcal', 'teeKcal'], true)) {
            return (int) round($cell['value']);
        }

        return $cell['value'];
    }

    /**
     * @param  list<array{item: ExtractedPdfTextItem, value: float, unit: string|null}>  $cells
     * @return list<list<array{item: ExtractedPdfTextItem, value: float, unit: string|null}>>
     */
    private function clusterByY(array $cells, float $tolerance): array
    {
        usort($cells, fn (array $a, array $b): int => $b['item']->y <=> $a['item']->y);

        $rows = [];

        foreach ($cells as $cell) {
            $placed = false;

            foreach ($rows as $index => $row) {
                if (abs($row[0]['item']->y - $cell['item']->y) <= $tolerance) {
                    $rows[$index][] = $cell;
                    $placed = true;

                    break;
                }
            }

            if (! $placed) {
                $rows[] = [$cell];
            }
        }

        return $rows;
    }

    private function normalizeGender(string $value): ?string
    {
        $value = mb_strtolower($value);

        return match (true) {
            in_array($value, ['male', 'm', '男', '男性'], true) => 'male',
            in_array($value, ['female', 'f', '女', '女性'], true) => 'female',
            default => null,
        };
    }
}
