<?php

namespace App\Services;

use App\Enums\MealType;
use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateMealEntryService
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
     *     note?: string|null,
     *     register_as_food?: bool
     * }  $data
     */
    public function handle(User $user, array $data): MealEntry
    {
        return DB::transaction(function () use ($user, $data): MealEntry {
            $quantity = round((float) $data['quantity'], 2);
            $snapshot = $this->resolveSnapshot($user, $data, $quantity);

            $foodItemId = $data['food_item_id'] ?? null;

            if (($data['register_as_food'] ?? false) === true && $foodItemId === null) {
                $foodItem = FoodItem::query()->create([
                    'user_id' => $user->id,
                    'name' => $snapshot['name'],
                    'serving_label' => '1食分',
                    'kcal' => $quantity > 0 ? round($snapshot['kcal'] / $quantity, 2) : $snapshot['kcal'],
                    'protein_g' => $quantity > 0 ? round($snapshot['protein_g'] / $quantity, 2) : $snapshot['protein_g'],
                    'fat_g' => $quantity > 0 ? round($snapshot['fat_g'] / $quantity, 2) : $snapshot['fat_g'],
                    'carb_g' => $quantity > 0 ? round($snapshot['carb_g'] / $quantity, 2) : $snapshot['carb_g'],
                ]);
                $foodItemId = $foodItem->id;
            }

            return MealEntry::query()->create([
                'user_id' => $user->id,
                'food_item_id' => $foodItemId,
                'eaten_on' => Carbon::parse($data['eaten_on'])->toDateString(),
                'meal_type' => MealType::from($data['meal_type']),
                'name' => $snapshot['name'],
                'quantity' => $quantity,
                'kcal' => $snapshot['kcal'],
                'protein_g' => $snapshot['protein_g'],
                'fat_g' => $snapshot['fat_g'],
                'carb_g' => $snapshot['carb_g'],
                'note' => $data['note'] ?? null,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, kcal: float, protein_g: float, fat_g: float, carb_g: float}
     */
    private function resolveSnapshot(User $user, array $data, float $quantity): array
    {
        $foodItemId = $data['food_item_id'] ?? null;

        if ($foodItemId !== null) {
            $foodItem = FoodItem::query()
                ->where('user_id', $user->id)
                ->whereKey($foodItemId)
                ->first();

            if ($foodItem === null) {
                throw ValidationException::withMessages([
                    'food_item_id' => ['指定されたマイ食品が見つかりません。'],
                ]);
            }

            return [
                'name' => $foodItem->name,
                'kcal' => round((float) $foodItem->kcal * $quantity, 2),
                'protein_g' => round((float) $foodItem->protein_g * $quantity, 2),
                'fat_g' => round((float) $foodItem->fat_g * $quantity, 2),
                'carb_g' => round((float) $foodItem->carb_g * $quantity, 2),
            ];
        }

        return [
            'name' => (string) ($data['name'] ?? ''),
            'kcal' => round((float) ($data['kcal'] ?? 0), 2),
            'protein_g' => round((float) ($data['protein_g'] ?? 0), 2),
            'fat_g' => round((float) ($data['fat_g'] ?? 0), 2),
            'carb_g' => round((float) ($data['carb_g'] ?? 0), 2),
        ];
    }
}
