<?php

namespace App\Services;

use App\Models\MealEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CopyPreviousDayMealsService
{
    /**
     * 前日の食事エントリを指定日へコピーする（ユーザー所有のみ）。
     *
     * @return array{copied: int}
     */
    public function handle(User $user, Carbon $targetDate): array
    {
        $sourceDate = $targetDate->copy()->subDay()->toDateString();
        $target = $targetDate->toDateString();

        return DB::transaction(function () use ($user, $sourceDate, $target): array {
            $sourceEntries = MealEntry::query()
                ->where('user_id', $user->id)
                ->whereDate('eaten_on', $sourceDate)
                ->orderBy('created_at')
                ->get();

            if ($sourceEntries->isEmpty()) {
                return ['copied' => 0];
            }

            $copied = 0;

            foreach ($sourceEntries as $entry) {
                MealEntry::query()->create([
                    'user_id' => $user->id,
                    'food_item_id' => $entry->food_item_id,
                    'eaten_on' => $target,
                    'meal_type' => $entry->meal_type,
                    'name' => $entry->name,
                    'quantity' => $entry->quantity,
                    'kcal' => $entry->kcal,
                    'protein_g' => $entry->protein_g,
                    'fat_g' => $entry->fat_g,
                    'carb_g' => $entry->carb_g,
                    'note' => $entry->note,
                ]);
                $copied++;
            }

            return ['copied' => $copied];
        });
    }
}
