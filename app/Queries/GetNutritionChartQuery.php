<?php

namespace App\Queries;

use App\Models\MealEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class GetNutritionChartQuery
{
    /**
     * 期間内の日別 SUM(kcal, P, F, C)。期間指定必須。
     *
     * @return Collection<int, array{date: string, kcal: float, protein_g: float, fat_g: float, carb_g: float}>
     */
    public function handle(User $user, Carbon $from, Carbon $to): Collection
    {
        if ($from->gt($to)) {
            throw new InvalidArgumentException('from must be on or before to.');
        }

        return MealEntry::query()
            ->where('user_id', $user->id)
            ->whereDate('eaten_on', '>=', $from->toDateString())
            ->whereDate('eaten_on', '<=', $to->toDateString())
            ->selectRaw('eaten_on as date')
            ->selectRaw('SUM(kcal) as kcal')
            ->selectRaw('SUM(protein_g) as protein_g')
            ->selectRaw('SUM(fat_g) as fat_g')
            ->selectRaw('SUM(carb_g) as carb_g')
            ->groupBy('eaten_on')
            ->orderBy('eaten_on')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'date' => Carbon::parse((string) $row->date)->toDateString(),
                'kcal' => round((float) $row->kcal, 2),
                'protein_g' => round((float) $row->protein_g, 2),
                'fat_g' => round((float) $row->fat_g, 2),
                'carb_g' => round((float) $row->carb_g, 2),
            ]);
    }
}
