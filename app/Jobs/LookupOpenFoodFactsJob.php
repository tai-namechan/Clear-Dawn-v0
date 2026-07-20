<?php

namespace App\Jobs;

use App\Enums\FoodLookupStatus;
use App\Models\FoodLookupRequest;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Open Food Facts でバーコード照合する（設計 §13.3 手順5）。
 * 画面リクエスト中に同期通信しない原則のため、必ず Queue 経由で実行する。
 * 取得 field は最小化し、attribution（出典表示）用に source を result へ残す。
 */
class LookupOpenFoodFactsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 15, 45];

    public int $uniqueFor = 300;

    private const FIELDS = 'product_name,product_name_ja,brands,serving_size,nutriments';

    public function __construct(public string $lookupRequestId) {}

    public function uniqueId(): string
    {
        return $this->lookupRequestId;
    }

    public function handle(): void
    {
        $lookup = FoodLookupRequest::query()->find($this->lookupRequestId);

        if ($lookup === null || $lookup->status !== FoodLookupStatus::Pending) {
            return;
        }

        $baseUrl = rtrim((string) config('services.openfoodfacts.base_url', 'https://world.openfoodfacts.org'), '/');

        $response = Http::withHeaders([
            // OFF の利用規約はアプリ特定可能な User-Agent を求める
            'User-Agent' => (string) config('services.openfoodfacts.user_agent', 'ClearDawn/1.0 (personal-use)'),
        ])
            ->timeout(15)
            ->get("{$baseUrl}/api/v2/product/{$lookup->barcode}.json", [
                'fields' => self::FIELDS,
            ]);

        if ($response->status() === 404) {
            $lookup->update([
                'status' => FoodLookupStatus::NotFound,
                'source' => 'openfoodfacts',
            ]);

            return;
        }

        // 404 以外の失敗はリトライへ（最終失敗は failed() で確定）
        $response->throw();

        $product = $response->json('product');

        if (! is_array($product)) {
            $lookup->update([
                'status' => FoodLookupStatus::NotFound,
                'source' => 'openfoodfacts',
            ]);

            return;
        }

        $lookup->update([
            'status' => FoodLookupStatus::Found,
            'source' => 'openfoodfacts',
            'result' => $this->mapProduct($product),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Open Food Facts lookup failed.', [
            'lookup_request_id' => $this->lookupRequestId,
            'error' => $exception?->getMessage(),
        ]);

        FoodLookupRequest::query()
            ->whereKey($this->lookupRequestId)
            ->where('status', FoodLookupStatus::Pending->value)
            ->update([
                'status' => FoodLookupStatus::Failed->value,
                'source' => 'openfoodfacts',
                'error_code' => 'provider_error',
            ]);
    }

    /**
     * serving 基準の栄養値が揃っていれば per=serving、無ければ per=100g で確認画面に渡す。
     * 欠損値は null のまま返し、確認フォーム側で入力必須にする（AI/外部値を自動確定しない）。
     *
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function mapProduct(array $product): array
    {
        $nutriments = is_array($product['nutriments'] ?? null) ? $product['nutriments'] : [];
        $servingSize = is_string($product['serving_size'] ?? null) ? trim($product['serving_size']) : '';

        $perServing = [
            'kcal' => $this->numeric($nutriments['energy-kcal_serving'] ?? null),
            'protein_g' => $this->numeric($nutriments['proteins_serving'] ?? null),
            'fat_g' => $this->numeric($nutriments['fat_serving'] ?? null),
            'carb_g' => $this->numeric($nutriments['carbohydrates_serving'] ?? null),
        ];

        $useServing = $servingSize !== '' && $perServing['kcal'] !== null;

        $values = $useServing ? $perServing : [
            'kcal' => $this->numeric($nutriments['energy-kcal_100g'] ?? null),
            'protein_g' => $this->numeric($nutriments['proteins_100g'] ?? null),
            'fat_g' => $this->numeric($nutriments['fat_100g'] ?? null),
            'carb_g' => $this->numeric($nutriments['carbohydrates_100g'] ?? null),
        ];

        $name = trim((string) ($product['product_name_ja'] ?? ''))
            ?: trim((string) ($product['product_name'] ?? ''));
        $brands = trim((string) ($product['brands'] ?? ''));

        return [
            'name' => $name !== '' ? $name : null,
            'brands' => $brands !== '' ? $brands : null,
            'serving_label' => $useServing ? $servingSize : '100g',
            'per' => $useServing ? 'serving' : '100g',
            ...$values,
        ];
    }

    private function numeric(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = round((float) $value, 2);

        return $number >= 0 ? $number : null;
    }
}
