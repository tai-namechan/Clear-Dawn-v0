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

            // unique(user_id, barcode) は soft delete 行にも効くため、
            // 過去に削除した同一バーコードは復元 + 上書きで再登録する。
            // barcode なし（成分表直接登録・PR-F2 入口2）は重複判定せず常に新規作成
            /** @var FoodItem|null $existing */
            $existing = $barcode === null ? null : FoodItem::withTrashed()
                ->where('user_id', $user->id)
                ->where('barcode', $barcode)
                ->first();

            $values = [
                'name' => $attributes['name'],
                'serving_label' => $attributes['serving_label'],
                'kcal' => $attributes['kcal'],
                'protein_g' => $attributes['protein_g'],
                'fat_g' => $attributes['fat_g'],
                'carb_g' => $attributes['carb_g'],
                'barcode' => $barcode,
                'barcode_type' => $barcodeType,
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
