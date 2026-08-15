<?php

namespace App\Queries;

use App\Enums\BodySegment;
use App\Enums\BodyStoryKind;
use App\Http\Resources\BodyMeasurementResource;
use App\Models\BodyMeasurement;
use App\Models\Metric;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Story画像用の表示データ組み立て。
 */
class GetBodyStoryExportQuery
{
    public function __construct(
        private GetNutritionChartQuery $nutritionChartQuery,
        private GetMetricChartQuery $metricChartQuery,
        private GetConfirmedBodyMeasurementQuery $bodyMeasurementQuery,
    ) {}

    /**
     * 既存記録から Story 表示用データを組み立てる。
     *
     * 食事・体重は既存テーブル、体組成は確定済み body_measurements を正本にする。
     *
     * @return array{
     *     kind: string,
     *     date: string,
     *     start_date: string,
     *     end_date: string,
     *     template_url: string,
     *     nutrition: array{
     *         calories_kcal: float|null,
     *         protein_g: float|null,
     *         fat_g: float|null,
     *         carbs_g: float|null,
     *         average_calories_kcal: float|null,
     *         average_protein_g: float|null,
     *         average_fat_g: float|null,
     *         average_carbs_g: float|null,
     *         calorie_history: array<int, array{date: string, calories_kcal: float}>
     *     },
     *     weight: array{
     *         weight_kg: float|null,
     *         previous_weight_kg: float|null,
     *         delta_kg: float|null,
     *         average_7d_kg: float|null,
     *         history: array<int, array{date: string, weight_kg: float}>
     *     },
     *     body: array{
     *         weight_kg: float|null,
     *         lean_body_mass_kg: float|null,
     *         skeletal_muscle_mass_kg: float|null,
     *         body_fat_percentage: float|null,
     *         abdominal_circumference_cm: float|null,
     *         measured_on: string|null,
     *         segments: array<string, array{lean_mass_kg: float|null, fat_mass_kg: float|null}>
     *     }
     * }
     */
    public function handle(User $user, Carbon $date, BodyStoryKind $kind): array
    {
        $end = $date->copy()->startOfDay();
        $start = $end->copy()->subDays(6);

        $nutritionHistory = $this->nutritionChartQuery->handle($user, $start, $end);
        $todayNutritionRow = $nutritionHistory->firstWhere('date', $end->toDateString());
        /** @var array{date: string, kcal: float, protein_g: float, fat_g: float, carb_g: float}|null $todayNutrition */
        $todayNutrition = is_array($todayNutritionRow) ? $todayNutritionRow : null;

        $weightMetric = Metric::query()
            ->where('key', 'weight')
            ->whereNull('user_id')
            ->first();

        /** @var Collection<int, array{date: string, value: string}> $weightHistory */
        $weightHistory = $weightMetric === null
            ? collect()
            : $this->metricChartQuery->handle($user, $weightMetric, $start, $end);

        $weightByDate = $weightHistory->keyBy('date');
        $todayWeightRow = $weightByDate->get($end->toDateString());
        $previousWeightRow = $weightByDate->get($end->copy()->subDay()->toDateString());
        $todayWeight = $this->floatOrNull(is_array($todayWeightRow) ? $todayWeightRow['value'] : null);
        $previousWeight = $this->floatOrNull(is_array($previousWeightRow) ? $previousWeightRow['value'] : null);

        return [
            'kind' => $kind->value,
            'date' => $end->toDateString(),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'template_url' => $kind->templateUrl(),
            'nutrition' => [
                'calories_kcal' => $todayNutrition !== null ? $this->floatOrNull($todayNutrition['kcal']) : null,
                'protein_g' => $todayNutrition !== null ? $this->floatOrNull($todayNutrition['protein_g']) : null,
                'fat_g' => $todayNutrition !== null ? $this->floatOrNull($todayNutrition['fat_g']) : null,
                'carbs_g' => $todayNutrition !== null ? $this->floatOrNull($todayNutrition['carb_g']) : null,
                'average_calories_kcal' => $nutritionHistory->isEmpty()
                    ? null
                    : round((float) $nutritionHistory->avg('kcal'), 0),
                'average_protein_g' => $nutritionHistory->isEmpty()
                    ? null
                    : round((float) $nutritionHistory->avg('protein_g'), 1),
                'average_fat_g' => $nutritionHistory->isEmpty()
                    ? null
                    : round((float) $nutritionHistory->avg('fat_g'), 1),
                'average_carbs_g' => $nutritionHistory->isEmpty()
                    ? null
                    : round((float) $nutritionHistory->avg('carb_g'), 1),
                'calorie_history' => $nutritionHistory
                    ->map(fn (array $row): array => [
                        'date' => $row['date'],
                        'calories_kcal' => (float) $row['kcal'],
                    ])
                    ->values()
                    ->all(),
            ],
            'weight' => [
                'weight_kg' => $todayWeight !== null ? round($todayWeight, 1) : null,
                'previous_weight_kg' => $previousWeight !== null ? round($previousWeight, 1) : null,
                'delta_kg' => $todayWeight !== null && $previousWeight !== null
                    ? round($todayWeight - $previousWeight, 1)
                    : null,
                'average_7d_kg' => $weightHistory->isEmpty()
                    ? null
                    : round((float) $weightHistory->avg('value'), 1),
                'history' => $weightHistory
                    ->map(fn (array $row): array => [
                        'date' => $row['date'],
                        'weight_kg' => round((float) $row['value'], 1),
                    ])
                    ->values()
                    ->all(),
            ],
            'body' => $this->bodyPayload($user, $end),
        ];
    }

    /**
     * @return array{
     *     weight_kg: float|null,
     *     lean_body_mass_kg: float|null,
     *     skeletal_muscle_mass_kg: float|null,
     *     body_fat_percentage: float|null,
     *     abdominal_circumference_cm: float|null,
     *     measured_on: string|null,
     *     segments: array<string, array{lean_mass_kg: float|null, fat_mass_kg: float|null}>
     * }
     */
    private function bodyPayload(User $user, Carbon $date): array
    {
        $measurement = $this->bodyMeasurementQuery->handle($user, $date);

        if ($measurement instanceof BodyMeasurement) {
            /** @var array{
             *     id: string,
             *     measured_on: string,
             *     weight_kg: float|null,
             *     lean_body_mass_kg: float|null,
             *     skeletal_muscle_mass_kg: float|null,
             *     body_fat_percentage: float|null,
             *     abdominal_circumference_cm: float|null,
             *     segments: array<string, array{lean_mass_kg: float|null, fat_mass_kg: float|null}>
             * } $resolved */
            $resolved = BodyMeasurementResource::make($measurement)->resolve();

            return [
                'weight_kg' => $resolved['weight_kg'],
                'lean_body_mass_kg' => $resolved['lean_body_mass_kg'],
                'skeletal_muscle_mass_kg' => $resolved['skeletal_muscle_mass_kg'],
                'body_fat_percentage' => $resolved['body_fat_percentage'],
                'abdominal_circumference_cm' => $resolved['abdominal_circumference_cm'],
                'measured_on' => $resolved['measured_on'],
                'segments' => $resolved['segments'],
            ];
        }

        $emptySegments = [];

        foreach (BodySegment::cases() as $segment) {
            $emptySegments[$segment->value] = [
                'lean_mass_kg' => null,
                'fat_mass_kg' => null,
            ];
        }

        return [
            'weight_kg' => null,
            'lean_body_mass_kg' => null,
            'skeletal_muscle_mass_kg' => null,
            'body_fat_percentage' => null,
            'abdominal_circumference_cm' => null,
            'measured_on' => null,
            'segments' => $emptySegments,
        ];
    }

    private function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
