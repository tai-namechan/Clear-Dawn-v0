<?php

namespace App\Services;

use App\Enums\MealType;
use App\Models\MealEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 個別の食事記録を指定日・指定食事区分へコピーする（元記録は変更しない）。
 */
class CopyMealEntryService
{
    /**
     * @param  array{eaten_on: string, meal_type?: string|null, quantity?: float|int|string|null, note?: string|null}  $data
     */
    public function handle(User $user, MealEntry $source, array $data): MealEntry
    {
        if ($source->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'meal_entry' => ['この食事記録をコピーする権限がありません。'],
            ]);
        }

        return DB::transaction(function () use ($user, $source, $data): MealEntry {
            $mealType = isset($data['meal_type']) && $data['meal_type'] !== null && $data['meal_type'] !== ''
                ? MealType::from((string) $data['meal_type'])
                : $source->meal_type;

            $quantity = isset($data['quantity']) && $data['quantity'] !== null && $data['quantity'] !== ''
                ? round((float) $data['quantity'], 2)
                : (float) $source->quantity;

            $ratio = (float) $source->quantity > 0
                ? $quantity / (float) $source->quantity
                : 1.0;

            return MealEntry::query()->create([
                'user_id' => $user->id,
                'food_item_id' => $source->food_item_id,
                'eaten_on' => Carbon::parse($data['eaten_on'])->toDateString(),
                'meal_type' => $mealType,
                'name' => $source->name,
                'quantity' => $quantity,
                'kcal' => round((float) $source->kcal * $ratio, 2),
                'protein_g' => round((float) $source->protein_g * $ratio, 2),
                'fat_g' => round((float) $source->fat_g * $ratio, 2),
                'carb_g' => round((float) $source->carb_g * $ratio, 2),
                'note' => array_key_exists('note', $data) ? $data['note'] : $source->note,
            ]);
        });
    }
}
