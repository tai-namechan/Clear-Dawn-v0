<?php

namespace App\Services;

use App\Enums\MealType;
use App\Models\FoodItem;
use App\Models\MealEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateMealEntryService
{
    /**
     * @param  array{
     *     eaten_on: string,
     *     meal_type: string,
     *     food_item_id?: string|null,
     *     name?: string,
     *     quantity: float|int|string,
     *     kcal?: float|int|string|null,
     *     protein_g?: float|int|string|null,
     *     fat_g?: float|int|string|null,
     *     carb_g?: float|int|string|null,
     *     note?: string|null
     * }  $data
     */
    public function handle(MealEntry $mealEntry, array $data): MealEntry
    {
        return DB::transaction(function () use ($mealEntry, $data): MealEntry {
            $quantity = round((float) $data['quantity'], 2);
            $foodItemId = $data['food_item_id'] ?? null;

            if ($foodItemId !== null) {
                $foodItem = FoodItem::query()
                    ->where('user_id', $mealEntry->user_id)
                    ->whereKey($foodItemId)
                    ->first();

                if ($foodItem === null) {
                    throw ValidationException::withMessages([
                        'food_item_id' => ['指定されたマイ食品が見つかりません。'],
                    ]);
                }

                $mealEntry->fill([
                    'food_item_id' => $foodItem->id,
                    'eaten_on' => Carbon::parse($data['eaten_on'])->toDateString(),
                    'meal_type' => MealType::from($data['meal_type']),
                    'name' => $foodItem->name,
                    'quantity' => $quantity,
                    'kcal' => round((float) $foodItem->kcal * $quantity, 2),
                    'protein_g' => round((float) $foodItem->protein_g * $quantity, 2),
                    'fat_g' => round((float) $foodItem->fat_g * $quantity, 2),
                    'carb_g' => round((float) $foodItem->carb_g * $quantity, 2),
                    'note' => $data['note'] ?? null,
                ]);
            } else {
                $mealEntry->fill([
                    'food_item_id' => null,
                    'eaten_on' => Carbon::parse($data['eaten_on'])->toDateString(),
                    'meal_type' => MealType::from($data['meal_type']),
                    'name' => (string) ($data['name'] ?? ''),
                    'quantity' => $quantity,
                    'kcal' => round((float) ($data['kcal'] ?? 0), 2),
                    'protein_g' => round((float) ($data['protein_g'] ?? 0), 2),
                    'fat_g' => round((float) ($data['fat_g'] ?? 0), 2),
                    'carb_g' => round((float) ($data['carb_g'] ?? 0), 2),
                    'note' => $data['note'] ?? null,
                ]);
            }

            $mealEntry->save();

            return $mealEntry->refresh();
        });
    }
}
