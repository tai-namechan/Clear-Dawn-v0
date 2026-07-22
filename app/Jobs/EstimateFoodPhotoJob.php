<?php

namespace App\Jobs;

use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\PromptTemplate;
use App\Domain\Shared\AI\QuotaExceededException;
use App\Enums\FoodLookupStatus;
use App\Models\FoodLookupRequest;
use App\Services\ChainNutritionScraper;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class EstimateFoodPhotoJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30];

    public int $uniqueFor = 600;

    private const SYSTEM_PROMPT = <<<'PROMPT'
あなたは管理栄養士レベルの知識を持つ、料理写真からの栄養成分推定アシスタントです。

## 出力形式
JSONのみを返してください。それ以外のテキストは一切不要です。
{"name": string, "serving_label": string, "per": "serving", "kcal": number, "protein_g": number, "fat_g": number, "carb_g": number, "store_name": string|null, "menu_name": string|null}

## 推定ルール

### 料理の特定
- 写真に写っている料理を具体的に特定する（例: "味噌ラーメン" "チキンカツカレー"）
- トッピングや具材も考慮に入れる（チャーシュー、煮卵、揚げ物など）
- パッケージ食品の場合は商品名とサイズを読み取る

### 店名・メニュー名の抽出（重要）
- 写真内に看板、ロゴ、メニュー表、レシート、のれん、箸袋、紙ナプキン等から店名が読み取れる場合は store_name に記入
- 写真やコンテキストからメニュー名が特定できる場合は menu_name に記入
- 読み取れない・特定できない場合は null を返す
- チェーン店名は正式名称で記入（例: "すき家", "松屋", "CoCo壱番屋"）

### カロリー推定の重要原則
- **外食・レストランの料理は家庭料理より大幅に高カロリーである**
- 調理油、バター、ラード、背脂、ドレッシング等の「見えない脂質」を必ず加算する
- 外食の1人前は一般的なレシピサイトの分量より1.3〜1.8倍多い
- **迷ったら高めに推定する**（ダイエット目的のユーザーにとって過少推定は有害）

### 代表的な外食カロリーの目安（実測値ベース）
- ラーメン（醤油・塩）: 700〜900 kcal
- ラーメン（味噌・豚骨）: 900〜1200 kcal
- チャーシュー麺: +150〜250 kcal
- つけ麺: 900〜1300 kcal
- カレーライス: 700〜1000 kcal
- カツカレー: 1100〜1400 kcal
- 牛丼（並）: 650〜750 kcal
- 天ぷら定食: 800〜1100 kcal
- ハンバーグ定食: 800〜1100 kcal
- 唐揚げ定食: 900〜1200 kcal
- パスタ（クリーム系）: 700〜1000 kcal
- ピザ（1枚）: 1500〜2500 kcal
- チャーハン: 600〜900 kcal

### PFC推定
- protein_g: 肉・魚・卵・豆腐などの量から推定
- fat_g: 調理法（揚げ・炒め）、ソース、チーズ、背脂を考慮。外食は脂質が多い
- carb_g: 麺・米・パン・芋の量から推定。ラーメンの麺は通常150g前後

### serving_label
- 写真に写っている量を記述（例: "1杯", "1人前", "1皿"）

### 推定不能な場合
- 食べ物でない写真、判別不能な場合のみ {"error":"unrecognizable"} を返す
PROMPT;

    public function __construct(public string $lookupRequestId) {}

    public function uniqueId(): string
    {
        return $this->lookupRequestId;
    }

    public function handle(AiGateway $ai, ChainNutritionScraper $scraper): void
    {
        $lookup = FoodLookupRequest::query()->find($this->lookupRequestId);

        if ($lookup === null || $lookup->status !== FoodLookupStatus::AiPending) {
            return;
        }

        $imagePath = $lookup->temp_image_path;
        if ($imagePath === null || ! Storage::disk($this->disk())->exists($imagePath)) {
            $this->finishFailed($lookup, 'photo_provider_error');

            return;
        }

        try {
            $completed = $ai->complete(
                userId: (int) $lookup->user_id,
                feature: 'meals.photo_estimate',
                prompt: PromptTemplate::make('meals.photo_estimate.v1', self::SYSTEM_PROMPT, ''),
                tier: 'strong',
                maxTokens: 512,
                messages: [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $this->mediaType($imagePath),
                                'data' => base64_encode((string) Storage::disk($this->disk())->get($imagePath)),
                            ],
                        ],
                        ['type' => 'text', 'text' => 'この料理の栄養成分を推定してください。'],
                    ],
                ]],
            );
        } catch (QuotaExceededException) {
            $this->finishFailed($lookup, 'photo_quota_exceeded');

            return;
        } catch (Throwable $e) {
            $this->retryOrFail($lookup, 'photo_provider_error', $e);

            return;
        }

        $decoded = $this->decodeJson($completed['text']);

        if (($decoded['error'] ?? null) === 'unrecognizable') {
            $this->finishFailed($lookup, 'photo_unrecognizable');

            return;
        }

        $result = $this->validateAndMap($decoded);

        if ($result === null) {
            $this->retryOrFail($lookup, 'photo_invalid_output', new RuntimeException('Photo estimate output failed schema validation.'));

            return;
        }

        $storeName = $this->extractString($decoded, 'store_name');
        $menuName = $this->extractString($decoded, 'menu_name');

        if ($storeName !== null && $menuName !== null) {
            $this->saveMenuMeta($lookup, $storeName, $menuName);

            $scraped = $scraper->search($storeName, $menuName);
            if ($scraped !== null) {
                $this->finishFound($lookup, [
                    'name' => $scraped['name'],
                    'brands' => null,
                    'serving_label' => $scraped['serving_label'],
                    'per' => $scraped['per'],
                    'kcal' => $scraped['kcal'],
                    'protein_g' => $scraped['protein_g'],
                    'fat_g' => $scraped['fat_g'],
                    'carb_g' => $scraped['carb_g'],
                ], 'nutrition_db');

                return;
            }
        }

        $this->finishFound($lookup, $result);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('EstimateFoodPhotoJob failed.', [
            'lookup_request_id' => $this->lookupRequestId,
            'error' => $exception?->getMessage(),
        ]);

        $lookup = FoodLookupRequest::query()->find($this->lookupRequestId);

        if ($lookup !== null && $lookup->status === FoodLookupStatus::AiPending) {
            $this->finishFailed($lookup, 'photo_provider_error');
        }
    }

    private function retryOrFail(FoodLookupRequest $lookup, string $errorCode, Throwable $e): void
    {
        if ($this->attempts() >= $this->tries) {
            Log::warning('EstimateFoodPhotoJob exhausted retries.', [
                'lookup_request_id' => $this->lookupRequestId,
                'error_code' => $errorCode,
                'error' => $e->getMessage(),
            ]);

            $this->finishFailed($lookup, $errorCode);

            return;
        }

        throw $e;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function finishFound(FoodLookupRequest $lookup, array $result, string $source = 'ai_photo_estimate'): void
    {
        $written = FoodLookupRequest::query()
            ->whereKey($lookup->id)
            ->where('status', FoodLookupStatus::AiPending->value)
            ->update([
                'status' => FoodLookupStatus::Found->value,
                'source' => $source,
                'result' => json_encode($result, JSON_UNESCAPED_UNICODE),
                'error_code' => null,
                'temp_image_path' => null,
            ]);

        if ($written === 1) {
            $this->deleteImage($lookup);
        }
    }

    private function finishFailed(FoodLookupRequest $lookup, string $errorCode): void
    {
        $written = FoodLookupRequest::query()
            ->whereKey($lookup->id)
            ->where('status', FoodLookupStatus::AiPending->value)
            ->update([
                'status' => FoodLookupStatus::Failed->value,
                'source' => 'ai_photo_estimate',
                'error_code' => $errorCode,
                'temp_image_path' => null,
            ]);

        if ($written === 1) {
            $this->deleteImage($lookup);
        }
    }

    private function deleteImage(FoodLookupRequest $lookup): void
    {
        if ($lookup->temp_image_path !== null) {
            Storage::disk($this->disk())->delete($lookup->temp_image_path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $text): array
    {
        $trimmed = trim($text);
        if (preg_match('/\{.*\}/s', $trimmed, $matches) === 1) {
            $trimmed = $matches[0];
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>|null
     */
    private function validateAndMap(array $decoded): ?array
    {
        $kcal = $this->numeric($decoded['kcal'] ?? null, 9999);
        if ($kcal === null) {
            return null;
        }

        $name = is_string($decoded['name'] ?? null)
            ? mb_substr(trim($decoded['name']), 0, 100)
            : null;

        $servingLabel = is_string($decoded['serving_label'] ?? null)
            ? mb_substr(trim($decoded['serving_label']), 0, 50)
            : '1人前';

        return [
            'name' => $name !== '' ? $name : null,
            'brands' => null,
            'serving_label' => $servingLabel !== '' ? $servingLabel : '1人前',
            'per' => 'serving',
            'kcal' => $kcal,
            'protein_g' => $this->numeric($decoded['protein_g'] ?? null, 999),
            'fat_g' => $this->numeric($decoded['fat_g'] ?? null, 999),
            'carb_g' => $this->numeric($decoded['carb_g'] ?? null, 999),
        ];
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    private function extractString(array $decoded, string $key): ?string
    {
        $value = $decoded[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, 100);
    }

    private function saveMenuMeta(FoodLookupRequest $lookup, string $storeName, string $menuName): void
    {
        $meta = is_array($lookup->meta) ? $lookup->meta : [];
        $meta['store_name'] = $storeName;
        $meta['menu_name'] = $menuName;

        FoodLookupRequest::query()
            ->whereKey($lookup->id)
            ->update(['meta' => json_encode($meta, JSON_UNESCAPED_UNICODE)]);
    }

    private function numeric(mixed $value, float $max): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = round((float) $value, 2);

        return ($number >= 0 && $number <= $max) ? $number : null;
    }

    private function mediaType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    private function disk(): string
    {
        return (string) config('meals.label_ocr.disk', 'local');
    }
}
