<?php

namespace App\Services;

use App\Models\FoodItem;
use App\Models\FoodLookupRequest;
use App\Models\User;
use App\Support\BarcodeNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * 照合結果のユーザー確認後に food_items へ保存する（設計 §13.3 手順7〜8）。
 * 外部DB/AIの値は自動確定せず、確認フォームで編集された値のみを保存する。
 * barcode は確認フォームの任意入力を優先し、空なら lookup に紐づく既存値を使う。
 */
class ConfirmFoodLookupService
{
    public function __construct(
        private readonly BarcodeNormalizer $normalizer,
    ) {}

    /**
     * @param  array{name: string, serving_label: string, kcal: float|int|string, protein_g: float|int|string, fat_g: float|int|string, carb_g: float|int|string, barcode?: string|null}  $attributes
     */
    public function handle(User $user, FoodLookupRequest $lookup, array $attributes): FoodItem
    {
        return DB::transaction(function () use ($user, $lookup, $attributes): FoodItem {
            [$barcode, $barcodeType] = $this->resolveBarcode($lookup, $attributes);
            [$storeName, $menuName] = $this->resolveMenuKey($lookup);

            /** @var FoodItem|null $existing */
            $existing = $this->findExisting($user, $barcode, $storeName, $menuName);

            $values = [
                'name' => $attributes['name'],
                'serving_label' => $attributes['serving_label'],
                'kcal' => $attributes['kcal'],
                'protein_g' => $attributes['protein_g'],
                'fat_g' => $attributes['fat_g'],
                'carb_g' => $attributes['carb_g'],
                'source' => $lookup->source,
                'barcode' => $barcode,
                'barcode_type' => $barcodeType,
                'store_name' => $storeName,
                'menu_name' => $menuName,
            ];

            if ($existing !== null) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $existing->fill($values);
                $existing->save();

                return $existing;
            }

            return FoodItem::query()->create([
                'user_id' => $user->id,
                ...$values,
            ]);
        });
    }

    private function findExisting(User $user, ?string $barcode, ?string $storeName, ?string $menuName): ?FoodItem
    {
        if ($barcode !== null) {
            return FoodItem::withTrashed()
                ->where('user_id', $user->id)
                ->where('barcode', $barcode)
                ->first();
        }

        if ($storeName !== null && $menuName !== null) {
            return FoodItem::withTrashed()
                ->where('user_id', $user->id)
                ->where('store_name', $storeName)
                ->where('menu_name', $menuName)
                ->first();
        }

        return null;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveMenuKey(FoodLookupRequest $lookup): array
    {
        $meta = $lookup->meta;
        if (! is_array($meta)) {
            return [null, null];
        }

        $store = $meta['store_name'] ?? null;
        $menu = $meta['menu_name'] ?? null;

        if (is_string($store) && $store !== '' && is_string($menu) && $menu !== '') {
            return [mb_substr($store, 0, 100), mb_substr($menu, 0, 100)];
        }

        return [null, null];
    }

    /**
     * @param  array{barcode?: string|null}  $attributes
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveBarcode(FoodLookupRequest $lookup, array $attributes): array
    {
        $raw = $attributes['barcode'] ?? null;

        if ($raw !== null && trim((string) $raw) !== '') {
            $normalized = $this->normalizer->normalize((string) $raw);

            // FormRequest で検証済み。ここは防御的に lookup へフォールバックしない。
            if ($normalized === null) {
                return [null, null];
            }

            return [$normalized['value'], $normalized['type']];
        }

        return [$lookup->barcode, $lookup->barcode_type];
    }
}
