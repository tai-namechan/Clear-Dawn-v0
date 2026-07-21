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

    public int $timeout = 180;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30];

    public int $uniqueFor = 600;

    private const SYSTEM_PROMPT = <<<'PROMPT'
あなたは管理栄養士レベルの知識を持つ、外食メニューの栄養成分推定アシスタントです。

## 出力形式
JSONのみを返してください。それ以外のテキストは一切不要です。
{"name": string, "serving_label": string, "per": "serving", "kcal": number, "protein_g": number, "fat_g": number, "carb_g": number}

## 推定ルール

### 情報源の優先順位（厳守）
1. **必ずウェブ検索を実行し、その店舗の公式栄養成分情報を探すこと**
   - 大手チェーン（すき家、松屋、吉野家、マクドナルド、CoCo壱番屋、餃子の王将、日高屋、幸楽苑、丸亀製麺、かつや、天丼てんや、ほっともっと、大戸屋、やよい軒、サイゼリヤ、ガスト、バーミヤン、モスバーガー、ケンタッキー、スシロー、くら寿司、はま寿司等）は公式サイトに栄養成分表がある
   - 検索クエリ例: 「{店名} {メニュー名} カロリー 栄養成分」「{店名} 栄養成分表」
   - 公式値が見つかった場合は**必ずその値を使う**（推定値で上書きしない）
   - サイズ違い（並盛・大盛・特盛など）に注意し、ユーザーが指定したサイズの値を使う
2. 公式情報が見つからない場合、類似チェーンの公式値から類推
3. いずれも見つからない場合のみ、一般的な外食として推定

### 推定時の重要原則（公式値がない場合のみ適用）
- **外食は家庭料理より大幅に高カロリーである**
- 調理油、バター、ラード、背脂、ドレッシング等の「見えない脂質」を必ず加算する
- 外食の1人前は一般的なレシピサイトの分量より1.3〜1.8倍多い
- **迷ったら高めに推定する**（ダイエット目的のユーザーにとって過少推定は有害）

### 代表的な外食カロリーの目安（実測値ベース、推定時の参考）
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

### name
- メニュー名をそのまま記入

### serving_label
- 通常の1食分を基準にする（例: "1人前", "レギュラーサイズ", "並盛"）
- サイズ指定がある場合はそのサイズを記入（例: "特盛", "大盛"）

### 推定不能な場合
- 架空の店名やメニュー名など、推定が全く不可能な場合のみ {"error":"unknown_menu"} を返す
- 一般的なジャンルから推定可能な場合は推定値を返す
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
                tools: [[
                    'type' => 'web_search_20250305',
                    'name' => 'web_search',
                    'max_uses' => 3,
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
