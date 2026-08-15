<?php

namespace App\Queries;

use App\Enums\BodyStoryKind;
use App\Models\Metric;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetBodyStoryExportQuery
{
    public function __construct(
        private GetNutritionChartQuery $nutritionChartQuery,
        private GetMetricChartQuery $metricChartQuery,
    ) {}

    /**
     * 既存 meal_entries / metric_records から Story 表示用データを組み立てる。
     * 書き込みはしない。Story 専用の記録テーブルは使わない。
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
