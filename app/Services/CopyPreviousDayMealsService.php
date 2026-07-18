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
     * 対象日に既存の記録がある場合は重複防止のためコピーしない（reason: target_not_empty）。
     *
     * @return array{copied: int, reason?: string}
     */
    public function handle(User $user, Carbon $targetDate): array
    {
        $sourceDate = $targetDate->copy()->subDay()->toDateString();
        $target = $targetDate->toDateString();

        return DB::transaction(function () use ($user, $sourceDate, $target): array {
            $targetHasEntries = MealEntry::query()
                ->where('user_id', $user->id)
                ->whereDate('eaten_on', $target)
                ->lockForUpdate()
                ->exists();

            if ($targetHasEntries) {
                return ['copied' => 0, 'reason' => 'target_not_empty'];
            }

            $sourceEntries = MealEntry::query()
                ->where('user_id', $user->id)
                ->whereDate('eaten_on', $sourceDate)
                ->orderBy('created_at')
                ->get();

            if ($sourceEntries->isEmpty()) {
                return ['copied' => 0, 'reason' => 'source_empty'];
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
