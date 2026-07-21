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
use RuntimeException;
use Throwable;

class EstimateFoodMenuJob implements ShouldBeUnique, ShouldQueue
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
あなたは外食メニューの栄養成分を推定するアシスタントです。JSONのみを返してください。
形式: {"name": string, "serving_label": string, "per": "serving", "kcal": number, "protein_g": number, "fat_g": number, "carb_g": number}
- name にはメニュー名を記入
- serving_label は通常の1食分を基準にする（例: "1人前", "レギュラーサイズ"）
- 公式の栄養情報がある場合はそれに基づく。ない場合は一般的なレシピから推定する
- 推定不能な場合は {"error":"unknown_menu"} のみを返す
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

        $meta = $lookup->meta;
        $storeName = $meta['store_name'] ?? null;
        $menuName = $meta['menu_name'] ?? null;

        if (! is_string($storeName) || ! is_string($menuName) || $storeName === '' || $menuName === '') {
            $this->finishFailed($lookup, 'menu_invalid_input');

            return;
        }

        try {
            $completed = $ai->complete(
                userId: (int) $lookup->user_id,
                feature: 'meals.menu_estimate',
                prompt: PromptTemplate::make('meals.menu_estimate.v1', self::SYSTEM_PROMPT, ''),
                tier: 'cheap',
                maxTokens: 512,
                messages: [[
                    'role' => 'user',
                    'content' => "店名: {$storeName}\nメニュー: {$menuName}\nこのメニューの栄養成分を推定してください。",
                ]],
            );
        } catch (QuotaExceededException) {
            $this->finishFailed($lookup, 'menu_quota_exceeded');

            return;
        } catch (Throwable $e) {
            $this->retryOrFail($lookup, 'menu_provider_error', $e);

            return;
        }

        $decoded = $this->decodeJson($completed['text']);

        if (($decoded['error'] ?? null) === 'unknown_menu') {
            $this->finishFailed($lookup, 'menu_unknown');

            return;
        }

        $result = $this->validateAndMap($decoded);

        if ($result === null) {
            $this->retryOrFail($lookup, 'menu_invalid_output', new RuntimeException('Menu estimate output failed schema validation.'));

            return;
        }

        $this->finishFound($lookup, $result);
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('EstimateFoodMenuJob failed.', [
            'lookup_request_id' => $this->lookupRequestId,
            'error' => $exception?->getMessage(),
        ]);

        $lookup = FoodLookupRequest::query()->find($this->lookupRequestId);

        if ($lookup !== null && $lookup->status === FoodLookupStatus::AiPending) {
            $this->finishFailed($lookup, 'menu_provider_error');
        }
    }

    private function retryOrFail(FoodLookupRequest $lookup, string $errorCode, Throwable $e): void
    {
        if ($this->attempts() >= $this->tries) {
            Log::warning('EstimateFoodMenuJob exhausted retries.', [
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
        FoodLookupRequest::query()
            ->whereKey($lookup->id)
            ->where('status', FoodLookupStatus::AiPending->value)
            ->update([
                'status' => FoodLookupStatus::Found->value,
                'source' => 'ai_menu_estimate',
                'result' => json_encode($result, JSON_UNESCAPED_UNICODE),
                'error_code' => null,
            ]);
    }

    private function finishFailed(FoodLookupRequest $lookup, string $errorCode): void
    {
        FoodLookupRequest::query()
            ->whereKey($lookup->id)
            ->where('status', FoodLookupStatus::AiPending->value)
            ->update([
                'status' => FoodLookupStatus::Failed->value,
                'source' => 'ai_menu_estimate',
                'error_code' => $errorCode,
            ]);
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
}
