<?php

namespace App\Services;

use App\Enums\FoodLookupStatus;
use App\Jobs\LookupOpenFoodFactsJob;
use App\Models\FoodItem;
use App\Models\FoodLookupRequest;
use App\Models\User;
use App\Support\BarcodeNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * バーコードスキャンの起点（設計 §13.3 手順2〜5）。
 * 自分の food_items に hit すれば即返し、miss なら照合リクエストを作って
 * Open Food Facts 照合 Job を afterCommit で dispatch する。
 */
class StartFoodBarcodeLookupService
{
    public function __construct(
        private readonly BarcodeNormalizer $normalizer,
    ) {}

    /**
     * @return array{status: 'hit', food: FoodItem}|array{status: 'pending', lookup: FoodLookupRequest}
     */
    public function handle(User $user, string $rawBarcode): array
    {
        $normalized = $this->normalizer->normalize($rawBarcode);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'barcode' => 'バーコードの形式が正しくありません（EAN-8 / UPC-A / EAN-13）。',
            ]);
        }

        $food = FoodItem::query()
            ->where('user_id', $user->id)
            ->where('barcode', $normalized['value'])
            ->first();

        if ($food !== null) {
            return ['status' => 'hit', 'food' => $food];
        }

        return DB::transaction(function () use ($user, $normalized): array {
            // 同一バーコードの未完了リクエストがあれば再利用する（連打で OFF を叩かない）
            $lookup = FoodLookupRequest::query()
                ->where('user_id', $user->id)
                ->where('barcode', $normalized['value'])
                ->where('status', FoodLookupStatus::Pending)
                ->where('expires_at', '>', now())
                ->first();

            if ($lookup === null) {
                $lookup = FoodLookupRequest::query()->create([
                    'user_id' => $user->id,
                    'barcode' => $normalized['value'],
                    'barcode_type' => $normalized['type'],
                    'status' => FoodLookupStatus::Pending,
                    'expires_at' => now()->addDay(),
                ]);

                DB::afterCommit(fn () => LookupOpenFoodFactsJob::dispatch($lookup->id));
            }

            return ['status' => 'pending', 'lookup' => $lookup];
        });
    }
}
