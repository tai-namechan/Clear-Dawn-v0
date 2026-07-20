<?php

namespace App\Jobs;

use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\PromptTemplate;
use App\Domain\Shared\AI\QuotaExceededException;
use App\Enums\FoodLookupStatus;
use App\Models\FoodLookupRequest;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * 成分表画像を AiGateway(vision, feature=meals.label_ocr, tier=cheap) で読み取る
 * （設計 §13.4 手順4〜7 / 完成設計 §3）。
 * 画面リクエスト中に AI 通信しない原則のため、必ず Queue 経由で実行する。
 * AI結果は自動確定しない: found にするだけで food_items へは confirm 経由でしか書かない。
 * 終端状態（found / failed）に達したら temp 画像を破棄する。
 */
class LookupFoodLabelOcrJob implements ShouldBeUnique, ShouldQueue
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
あなたは食品の栄養成分表示を読み取るアシスタントです。JSONのみを返してください。
形式: {"serving_label": string|null, "per": "serving"|"100g", "kcal": number|null, "protein_g": number|null, "fat_g": number|null, "carb_g": number|null}
- 表示が「1食(1本/1袋/1個 等)あたり」なら per="serving"、serving_label にその単位（例:"1本(500ml)"）を入れる
- 表示が「100gあたり」「100mlあたり」なら per="100g"
- 炭水化物は糖質+食物繊維の合計（炭水化物の記載があればそれを使う）
- 判読できない値は null
- 画像が栄養成分表示でない、または判読不能な場合は {"error":"unreadable"} のみを返す
PROMPT;

    public function __construct(public string $lookupRequestId) {}

    public function uniqueId(): string
    {
        return $this->lookupRequestId;
    }

    public function handle(AiGateway $ai): void
    {
        $lookup = FoodLookupRequest::query()->find($this->lookupRequestId);

        if ($lookup === null || $lookup->status !== FoodLookupStatus::OcrPending) {
            return;
        }

        $imagePath = $lookup->temp_image_path;
        if ($imagePath === null || ! Storage::disk($this->disk())->exists($imagePath)) {
            $this->finishFailed($lookup, 'ocr_provider_error');

            return;
        }

        try {
            $completed = $ai->complete(
                userId: (int) $lookup->user_id,
                feature: 'meals.label_ocr',
                prompt: PromptTemplate::make('meals.label_ocr.v1', self::SYSTEM_PROMPT, ''),
                tier: 'cheap',
                maxTokens: 300,
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
                        ['type' => 'text', 'text' => 'この栄養成分表示を読み取ってください。'],
                    ],
                ]],
            );
        } catch (QuotaExceededException) {
            // 予約時点で弾かれた＝課金なし。リトライしても結果は同じなので即終端
            $this->finishFailed($lookup, 'ocr_quota_exceeded');

            return;
        } catch (Throwable $e) {
            $this->retryOrFail($lookup, 'ocr_provider_error', $e);

            return;
        }

        $decoded = $this->decodeJson($completed['text']);

        if (($decoded['error'] ?? null) === 'unreadable') {
            // モデルが成分表でない/判読不能と判定。再送しても同じ画像では変わらない
            $this->finishFailed($lookup, 'ocr_unreadable');

            return;
        }

        $result = $this->validateAndMap($decoded);

        if ($result === null) {
            $this->retryOrFail($lookup, 'ocr_invalid_output', new RuntimeException('OCR output failed schema validation.'));

            return;
        }

        $this->finishFound($lookup, $result);
    }

    /**
     * timeout / worker kill の安全網。ocr_pending のままなら失敗で終端し画像を破棄する。
     */
    public function failed(?Throwable $exception): void
    {
        Log::warning('LookupFoodLabelOcrJob failed.', [
            'lookup_request_id' => $this->lookupRequestId,
            'error' => $exception?->getMessage(),
        ]);

        $lookup = FoodLookupRequest::query()->find($this->lookupRequestId);

        if ($lookup !== null && $lookup->status === FoodLookupStatus::OcrPending) {
            $this->finishFailed($lookup, 'ocr_provider_error');
        }
    }

    /**
     * リトライ余地があれば例外を投げて再試行、最終試行なら終端させる。
     */
    private function retryOrFail(FoodLookupRequest $lookup, string $errorCode, Throwable $e): void
    {
        if ($this->attempts() >= $this->tries) {
            Log::warning('LookupFoodLabelOcrJob exhausted retries.', [
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
    private function finishFound(FoodLookupRequest $lookup, array $result): void
    {
        // stale Job が新しい試行を上書きしないよう条件付きUPDATE（F1/Kioku と同じ）
        $written = FoodLookupRequest::query()
            ->whereKey($lookup->id)
            ->where('status', FoodLookupStatus::OcrPending->value)
            ->update([
                'status' => FoodLookupStatus::Found->value,
                'source' => 'label_ocr',
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
            ->where('status', FoodLookupStatus::OcrPending->value)
            ->update([
                'status' => FoodLookupStatus::Failed->value,
                'source' => 'label_ocr',
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
     * 確認フォームが F1 の result と同じ形で扱えるよう検証しつつマップする。
     * kcal すら読めていない場合は unreadable 相当の invalid とする。
     *
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>|null
     */
    private function validateAndMap(array $decoded): ?array
    {
        $per = $decoded['per'] ?? null;
        if (! in_array($per, ['serving', '100g'], true)) {
            return null;
        }

        $kcal = $this->numeric($decoded['kcal'] ?? null, 9999);
        if ($kcal === null) {
            return null;
        }

        $servingLabel = is_string($decoded['serving_label'] ?? null)
            ? mb_substr(trim($decoded['serving_label']), 0, 50)
            : '';

        return [
            'name' => null,
            'brands' => null,
            'serving_label' => $servingLabel !== '' ? $servingLabel : ($per === 'serving' ? '1食分' : '100g'),
            'per' => $per,
            'kcal' => $kcal,
            'protein_g' => $this->numeric($decoded['protein_g'] ?? null, 999),
            'fat_g' => $this->numeric($decoded['fat_g'] ?? null, 999),
            'carb_g' => $this->numeric($decoded['carb_g'] ?? null, 999),
        ];
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
