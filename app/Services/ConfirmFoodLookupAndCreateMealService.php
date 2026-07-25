<?php

namespace App\Services;

use App\Models\FoodItem;
use App\Models\FoodLookupRequest;
use App\Models\MealEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 照合結果をマイ食品へ保存し、続けて食事記録を作成するユースケース。
 * 連打時は lookup.meta に保存した meal_entry_id で冪等にする。
 */
class ConfirmFoodLookupAndCreateMealService
{
    public function __construct(
        private readonly ConfirmFoodLookupService $confirmFoodLookup,
        private readonly CreateMealEntryService $createMealEntry,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     serving_label: string,
     *     kcal: float|int|string,
     *     protein_g: float|int|string,
     *     fat_g: float|int|string,
     *     carb_g: float|int|string,
     *     barcode?: string|null,
     *     brand?: string|null,
     *     nutrition_basis?: string|null,
     *     basis_amount?: float|int|string|null,
     *     basis_unit?: string|null,
     *     package_amount?: float|int|string|null,
     *     package_unit?: string|null,
     *     eaten_on: string,
     *     meal_type: string,
     *     quantity: float|int|string,
     *     note?: string|null
     * }  $attributes
     * @return array{food: FoodItem, entry: MealEntry, created: bool}
     */
    public function handle(User $user, FoodLookupRequest $lookup, array $attributes): array
    {
        return DB::transaction(function () use ($user, $lookup, $attributes): array {
            $lookup = FoodLookupRequest::query()
                ->whereKey($lookup->id)
                ->lockForUpdate()
                ->firstOrFail();

            $meta = is_array($lookup->meta) ? $lookup->meta : [];
            $existingEntryId = $meta['meal_entry_id'] ?? null;

            if (is_string($existingEntryId) && $existingEntryId !== '') {
                $existingEntry = MealEntry::query()
                    ->where('user_id', $user->id)
                    ->whereKey($existingEntryId)
                    ->first();

                if ($existingEntry !== null) {
                    $food = FoodItem::query()
                        ->where('user_id', $user->id)
                        ->whereKey($existingEntry->food_item_id)
                        ->first();

                    if ($food !== null) {
                        return [
                            'food' => $food,
                            'entry' => $existingEntry,
                            'created' => false,
                        ];
                    }
                }
            }

            $food = $this->confirmFoodLookup->handle($user, $lookup, $attributes);

            $entry = $this->createMealEntry->handle($user, [
                'eaten_on' => $attributes['eaten_on'],
                'meal_type' => $attributes['meal_type'],
                'food_item_id' => $food->id,
                'quantity' => $attributes['quantity'],
                'note' => $attributes['note'] ?? null,
            ]);

            $meta['meal_entry_id'] = $entry->id;
            $lookup->meta = $meta;
            $lookup->save();

            return [
                'food' => $food,
                'entry' => $entry,
                'created' => true,
            ];
        });
    }
}
