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
あなたは料理の写真から栄養成分を推定するアシスタントです。JSONのみを返してください。
形式: {"name": string, "serving_label": string, "per": "serving", "kcal": number, "protein_g": number, "fat_g": number, "carb_g": number}
- 料理名を日本語で記入（例: "チキンカレー", "サーモン丼"）
- serving_label は写真に写っている量を記述（例: "1皿", "1人前"）
- 推定不能な場合は {"error":"unrecognizable"} のみを返す
PROMPT;

    public function __construct(public string $lookupRequestId) {}

    public function uniqueId(): string
    {
        return $this->lookupRequestId;
    }

    public function handle(AiGateway $ai): void
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
                tier: 'cheap',
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
    private function finishFound(FoodLookupRequest $lookup, array $result): void
    {
        $written = FoodLookupRequest::query()
            ->whereKey($lookup->id)
            ->where('status', FoodLookupStatus::AiPending->value)
            ->update([
                'status' => FoodLookupStatus::Found->value,
                'source' => 'ai_photo_estimate',
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
