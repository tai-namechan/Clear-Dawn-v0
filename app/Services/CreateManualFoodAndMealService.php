<?php

namespace App\Services;

use App\Enums\FoodConfirmationStatus;
use App\Enums\NutritionBasis;
use App\Models\FoodItem;
use App\Models\MealEntry;
use App\Models\User;
use App\Support\BarcodeNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * OFF未検出後の手入力登録 / 今回だけ直接入力。
 */
class CreateManualFoodAndMealService
{
    public function __construct(
        private readonly BarcodeNormalizer $normalizer,
        private readonly CreateMealEntryService $createMealEntry,
    ) {}

    /**
     * @param  array{
     *     save_mode: string,
     *     name: string,
     *     serving_label?: string|null,
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
     *     eaten_on?: string|null,
     *     meal_type?: string|null,
     *     quantity?: float|int|string|null,
     *     note?: string|null
     * }  $data
     * @return array{food: FoodItem|null, entry: MealEntry|null}
     */
    public function handle(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data): array {
            $mode = $data['save_mode'];

            if ($mode === 'one_off') {
                $quantity = round((float) ($data['quantity'] ?? 1), 2);
                $entry = $this->createMealEntry->handle($user, [
                    'eaten_on' => $data['eaten_on'],
                    'meal_type' => $data['meal_type'],
                    'name' => $data['name'],
                    'quantity' => $quantity,
                    'kcal' => $data['kcal'],
                    'protein_g' => $data['protein_g'],
                    'fat_g' => $data['fat_g'],
                    'carb_g' => $data['carb_g'],
                    'note' => $data['note'] ?? null,
                ]);

                return ['food' => null, 'entry' => $entry];
            }

            $food = $this->upsertFood($user, $data);

            $entry = null;

            if ($mode === 'food_and_meal') {
                $entry = $this->createMealEntry->handle($user, [
                    'eaten_on' => $data['eaten_on'],
                    'meal_type' => $data['meal_type'],
                    'food_item_id' => $food->id,
                    'quantity' => $data['quantity'],
                    'note' => $data['note'] ?? null,
                ]);
            }

            return ['food' => $food, 'entry' => $entry];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertFood(User $user, array $data): FoodItem
    {
        $barcode = null;
        $barcodeType = null;
        $raw = $data['barcode'] ?? null;

        if ($raw !== null && trim((string) $raw) !== '') {
            $normalized = $this->normalizer->normalize((string) $raw);

            if ($normalized !== null) {
                $barcode = $normalized['value'];
                $barcodeType = $normalized['type'];
            }
        }

        $values = [
            'name' => $data['name'],
            'serving_label' => $data['serving_label'] ?? '1食分',
            'kcal' => $data['kcal'],
            'protein_g' => $data['protein_g'],
            'fat_g' => $data['fat_g'],
            'carb_g' => $data['carb_g'],
            'source' => 'manual',
            'barcode' => $barcode,
            'barcode_type' => $barcodeType,
            'brand' => isset($data['brand']) && is_string($data['brand']) && $data['brand'] !== ''
                ? mb_substr($data['brand'], 0, 100)
                : null,
            'nutrition_basis' => NutritionBasis::tryFrom((string) ($data['nutrition_basis'] ?? ''))?->value,
            'basis_amount' => $data['basis_amount'] ?? null,
            'basis_unit' => $data['basis_unit'] ?? null,
            'package_amount' => $data['package_amount'] ?? null,
            'package_unit' => $data['package_unit'] ?? null,
            'confirmation_status' => FoodConfirmationStatus::Manual->value,
            'confirmed_at' => now(),
        ];

        if ($barcode !== null) {
            $existing = FoodItem::withTrashed()
                ->where('user_id', $user->id)
                ->where('barcode', $barcode)
                ->first();

            if ($existing !== null) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->fill($values);
                $existing->save();

                return $existing;
            }
        }

        return FoodItem::query()->create([
            'user_id' => $user->id,
            ...$values,
        ]);
    }
}
