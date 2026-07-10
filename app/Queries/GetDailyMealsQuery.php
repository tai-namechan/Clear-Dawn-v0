<?php

namespace App\Queries;

use App\Enums\MealType;
use App\Models\MealEntry;
use App\Models\NutritionGoal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetDailyMealsQuery
{
    /**
     * 指定日のエントリを区分別グルーピング + 区分小計 + 日次合計 + 目標値。
     *
     * @return array{
     *     date: string,
     *     sections: array<int, array{
     *         meal_type: string,
     *         label: string,
     *         entries: array<int, MealEntry>,
     *         subtotal: array{kcal: float, protein_g: float, fat_g: float, carb_g: float}
     *     }>,
     *     totals: array{kcal: float, protein_g: float, fat_g: float, carb_g: float},
     *     goal: NutritionGoal|null
     * }
     */
    public function handle(User $user, Carbon $date): array
    {
        /** @var Collection<int, MealEntry> $entries */
        $entries = MealEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('eaten_on', $date->toDateString())
            ->orderBy('created_at')
            ->get();

        $grouped = $entries->groupBy(fn (MealEntry $entry): string => $entry->meal_type->value);

        $sections = [];
        $totals = ['kcal' => 0.0, 'protein_g' => 0.0, 'fat_g' => 0.0, 'carb_g' => 0.0];

        foreach (MealType::cases() as $mealType) {
            /** @var Collection<int, MealEntry> $sectionEntries */
            $sectionEntries = $grouped->get($mealType->value, collect());

            $subtotal = [
                'kcal' => round((float) $sectionEntries->sum('kcal'), 2),
                'protein_g' => round((float) $sectionEntries->sum('protein_g'), 2),
                'fat_g' => round((float) $sectionEntries->sum('fat_g'), 2),
                'carb_g' => round((float) $sectionEntries->sum('carb_g'), 2),
            ];

            $totals['kcal'] = round($totals['kcal'] + $subtotal['kcal'], 2);
            $totals['protein_g'] = round($totals['protein_g'] + $subtotal['protein_g'], 2);
            $totals['fat_g'] = round($totals['fat_g'] + $subtotal['fat_g'], 2);
            $totals['carb_g'] = round($totals['carb_g'] + $subtotal['carb_g'], 2);

            $sections[] = [
                'meal_type' => $mealType->value,
                'label' => $mealType->label(),
                'entries' => array_values($sectionEntries->all()),
                'subtotal' => $subtotal,
            ];
        }

        $goal = NutritionGoal::query()
            ->where('user_id', $user->id)
            ->first();

        return [
            'date' => $date->toDateString(),
            'sections' => $sections,
            'totals' => $totals,
            'goal' => $goal,
        ];
    }
}
