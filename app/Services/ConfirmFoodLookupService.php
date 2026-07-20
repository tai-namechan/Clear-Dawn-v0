<?php

namespace App\Services;

use App\Models\FoodItem;
use App\Models\FoodLookupRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 照合結果のユーザー確認後に food_items へ保存する（設計 §13.3 手順7〜8）。
 * 外部DB/AIの値は自動確定せず、確認フォームで編集された値のみを保存する。
 */
class ConfirmFoodLookupService
{
    /**
     * @param  array{name: string, serving_label: string, kcal: float|int|string, protein_g: float|int|string, fat_g: float|int|string, carb_g: float|int|string}  $attributes
     */
    public function handle(User $user, FoodLookupRequest $lookup, array $attributes): FoodItem
    {
        return DB::transaction(function () use ($user, $lookup, $attributes): FoodItem {
            // unique(user_id, barcode) は soft delete 行にも効くため、
            // 過去に削除した同一バーコードは復元 + 上書きで再登録する
            /** @var FoodItem|null $existing */
            $existing = FoodItem::withTrashed()
                ->where('user_id', $user->id)
                ->where('barcode', $lookup->barcode)
                ->first();

            $values = [
                'name' => $attributes['name'],
                'serving_label' => $attributes['serving_label'],
                'kcal' => $attributes['kcal'],
                'protein_g' => $attributes['protein_g'],
                'fat_g' => $attributes['fat_g'],
                'carb_g' => $attributes['carb_g'],
                'barcode' => $lookup->barcode,
                'barcode_type' => $lookup->barcode_type,
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
}
