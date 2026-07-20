# PR-F2 成分表OCR — 実装計画書

作成: 2026-07-20 / ブランチ: `feature/meals-label-ocr-f2`（`cursor/meals-barcode-lookup-a703` = PR #143 上にスタック）
根拠設計: `docs/design/ai-features-completion-design.md` §3 / `docs/design/ai-features-implementation-plan.md` §2.11・§13.4
F1 現状: `docs/dev/2026-07-20-pr-f1-handoff-for-cursor.md`

---

## 概要

F1（バーコード → Open Food Facts）が miss（`not_found` / `failed`）のとき、
栄養成分表示を撮影 → private storage → OCR Job（AiGateway vision, feature=`meals.label_ocr`, tier=cheap）
→ JSON schema 検証 → 確認フォーム → ユーザー確定後だけ `food_items` 保存、までを実装する。

## 非目標（本PRでやらない）

- 商品DBの外部購入
- 料理写真からの栄養推定
- 画像の永続保存（解析ライフサイクル終端で破棄。明示同意付き保存機能は将来）
- OFF / OCR の Web リクエスト中同期実行
- HEIC のサーバー側変換（クライアント canvas 再エンコード + iOS の自動 JPEG 変換に依存）

---

## 現状（F1からの再利用点・コード確認済み）

| 資産 | 状態 | F2での扱い |
|---|---|---|
| `food_lookup_requests` | `temp_image_path` / `expires_at` / `source` / `result` / `error_code` 列が既にある | **そのまま再利用**（新テーブル不要） |
| `FoodLookupStatus` enum | pending / found / not_found / failed | `OcrPending = 'ocr_pending'` を追加 |
| `FoodBarcodeLookupController@show` | status / result / source / error_code を返す | 変更なしで OCR 結果も返る（`found` の形が同じため） |
| `ConfirmFoodLookupService` | status=Found ゲート + barcode 紐づけ + soft-delete 復元 | **変更なしで再利用**（OCR 結果も同じ result 形にする） |
| `BarcodeLookupModal.vue` | scan / polling / confirm / hit の4ステップ + polling 境界 | miss 時に OCR 導線を追加、polling は `ocr_pending` も継続対象に |
| `meals:prune-expired-lookups` | 期限切れ行を delete | **temp 画像の削除を追加**（行削除前にファイル削除） |
| `AiGateway` | reserve → in_flight → HTTP → settle/release。**vision 未対応（content: string 前提）** | content blocks を通せるよう PHPDoc 型を緩和（後述E） |
| `tests/TestCase.php` | `Http::preventStrayRequests()` + `anthropicFakePattern()` | Job テストでそのまま使用 |
| Kioku `CaptureMemoryService` | private disk へ store → DB tx → 失敗時ファイル削除 | アップロードの実装パターンとして踏襲 |
| Kioku `TranscribeMemoryAudioJob` / `EnrichMemoryJob` | 条件付きUPDATEによる claim / 課金リトライ設計 | Job の claim・失敗分類パターンとして踏襲 |

docs との差分: 実装計画 §13.2 は「AI OCR requestはPR-A ledgerへ自動連携」とあり、AiGateway 経由で自動的に満たされる。完成設計 §3 の「写真は解析後に破棄」は「**終端状態（found / failed 確定）到達時に即削除**、期限切れは prune が安全網」と解釈する（リトライ中はファイルが必要なため）→ 未決事項Q3。

---

## 決定事項（論点A〜I）

### A. エントリポイント（2026-07-20 オーナー承認で拡張）

**採用: F1 の `BarcodeLookupModal` 内に統合**（別モーダルにしない）。入口は3系統。

1. **バーコードあり**（スキャン/手入力）→ 自DB → OFF → miss 時に「成分表を撮影」CTA → `ocr_capture` ステップ
2. **バーコードなし**（無い/読めない商品）→ scan ステップの副ボタン「成分表を撮影して登録」→ 直接 `ocr_capture`（barcode=null の lookup を新規作成）
3. 全部手入力（既存の直接入力）→ 変更なし

- 不変原則: **バーコードがあるなら無料の OFF 照合を必ず先に試す。有料 AI OCR は最後の手段**（バーコードあり時に OFF をスキップして OCR へ直行する導線は作らない）
- 理由: polling・confirm・エラーメッセージ枠を全ステップで共有でき、lookupId の受け渡しが単純

### B. データモデル

**採用: `food_lookup_requests` を共用**（§13.2 の設計どおり。`temp_image_path` は最初からF2用に用意されている）。

- status 追加: `OcrPending = 'ocr_pending'` のみ。`awaiting_image` は作らない（画像が来るまで行の状態は F1 の終端 `not_found` / `failed` のままでよい）。
- 遷移（入口1・添付）: `not_found | failed` → *(upload)* → `ocr_pending` → *(Job)* → `found`（source=`label_ocr`）| `failed`（error_code で区別）。**条件付きUPDATE**で二重アップロード競合を排除（Kioku claim パターン）。
- 遷移（入口2・新規）: *(upload)* で `barcode=null, status=ocr_pending` の行を新規作成 → 以降同じ。
- **migration（1本）**: `food_lookup_requests.barcode` / `barcode_type` を nullable 化（入口2用）。status は string カラムなので enum ケース追加に migration 不要。
- `result` は F1 と同形 `{name, brands, serving_label, per, kcal, protein_g, fat_g, carb_g}`（OCR では name/brands は原則 null → confirm フォームの必須入力が担保）。
- `error_code`（F2 追加分）: `ocr_quota_exceeded` / `ocr_unreadable` / `ocr_invalid_output` / `ocr_provider_error`。
- barcode 紐づけ: **confirm 時**。`ConfirmFoodLookupService` は barcode=null のとき既存行の重複判定をスキップして新規作成（`food_items` の `unique(user_id, barcode)` は NULL 複数許容なので制約問題なし）。

### C. ストレージ

**本番制約（Laravel Cloud 構成確認済み・2026-07-20）**: Web（App cluster）と Queue worker（Managed queue）は別コンテナで**ローカルFSを共有しない**。temp 画像を local disk に置くと Web がアップロードした画像を worker が読めないため、**本番は Object Storage（バケット）必須**。kioku-audio と同一パターンを踏襲する。

- disk: `config('meals.label_ocr.disk')` = `env('MEALS_LABEL_OCR_DISK', 'local')`。新規 config `config/meals.php`（kioku.php の audio 節と同型）。
- `config/filesystems.php` に local スタブ disk `food-label-ocr` を追加（kioku-audio と同型コメント付き。Laravel Cloud では同名 disk のバケットを作成すると `LARAVEL_CLOUD_DISK_CONFIG` 注入で上書きされる）。
- **デプロイ前チェック（Phase 7）**: Laravel Cloud に `food-label-ocr` disk のバケットを作成し、production env に `MEALS_LABEL_OCR_DISK=food-label-ocr` を設定。ローカル開発は default `local` のままで動く。
- パス: `food-label-ocr/{user_id}/{lookup_id}.{ext}` → `temp_image_path` に保存。
- TTL: 行の `expires_at`（既存 24h）に従属。独自 TTL は持たない。
- 削除トリガ（4系統すべて実装）:
  1. **Job 終端**（found / failed 確定）: Job 内で削除し `temp_image_path` を null 化
  2. **再アップロード**（failed → 再撮影）: 旧ファイルを削除してから新ファイル保存
  3. **期限切れ**: `meals:prune-expired-lookups` を拡張し、行削除前に `temp_image_path` のファイルを削除
  4. **ユーザー放棄**（モーダルを閉じただけ）: 追加処理なし。3 が回収する
- 「解析後破棄」との整合: リトライ（transient failure）中はファイルが必要なため、破棄は**終端状態到達時**。放置されても 24h + daily prune で消える。

### D. Upload API（2本）

```
POST /meals/barcode-lookup/{lookupId}/label-image   入口1: 既存lookupへ添付   (auth+verified, throttle:10,1)
POST /meals/label-ocr                                入口2: barcodeなし新規     (auth+verified, throttle:10,1)
```

- FormRequest `StoreFoodLabelImageRequest`（両エンドポイント共用）:
  - `image`: `required` / `image` / `mimes:jpeg,png,webp` / `max:5120`(KB) / `dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000`
  - 根拠: Anthropic vision の上限（≦5MB, ≦8000px）を **validate 時点で保証**し、Job 側の失敗要因を消す
- Service `StartFoodLabelOcrService`（F1 の Service 慣習に従い新設。controller は薄く）:
  - `attachToLookup(User, string $lookupId, UploadedFile)`:
    1. `user_id` スコープ + `whereKey` + `whereIn('status', [NotFound, Failed])` → 見つからなければ 404
    2. quota 事前チェック（`AiGateway::assertWithinQuota`）
    3. ファイルを disk へ store（Kioku パターン: **store → DB → 失敗時ファイル削除**）。旧 `temp_image_path` があれば削除（failed 再撮影）
    4. 条件付きUPDATE で `status=ocr_pending, temp_image_path=..., source=null, result=null, error_code=null, expires_at=now()+24h`。0行なら保存済みファイルを削除して 409
    5. `DB::afterCommit` で `LookupFoodLabelOcrJob::dispatch`
  - `startWithoutBarcode(User, UploadedFile)`: quota チェック → store → `barcode=null, status=ocr_pending` の行を新規作成 → dispatch
- レスポンス: 202 `{"status":"ocr_pending","lookup_id":"..."}`（両方。入口2は polling に lookup_id が必須）
- quota 超過: 422 `{"message":"今月のAI利用枠を使い切りました。..."}`（画像保存前に弾く）。Job 側の reserve が最終権威。

### E. OCR Job

- クラス: `App\Jobs\LookupFoodLabelOcrJob`（default Queue。personal 規模で dedicated 不要 — §13.4 の選択肢から default を採用）
- **本番 Queue 前提（確認済み 2026-07-20）**: Managed queue「integrations」は default キューも処理する（本番で kioku 文字起こしが動作する実績をオーナー確認済み）。F1 OFF 照合・本 Job は default キューのままでよい。
- **コールドスタート**: worker は 0-1 台のゼロスケール構成のため、Job 開始まで数十秒かかることがある。ポーリングは既存間隔のままとし、モーダル文言で「読み取りに数十秒かかることがあります」と案内（G 参照）。
- `tries=2` / `backoff=[10, 30]` / `timeout=120`（AI HTTP timeout 60s + 余裕）/ `ShouldBeUnique`(`uniqueId=lookupRequestId`, `uniqueFor=600`)
- ガード: `status !== OcrPending` なら return（F1 Job と同型）。`temp_image_path` が null / ファイル不存在なら `failed('ocr_provider_error')` 相当で終端。
- **AiGateway 契約**: `complete()` の `$messages` PHPDoc を `list<array{role: string, content: string|list<array<string, mixed>>}>` に緩和する**だけ**（実装は `$body['messages']` へパススルーのため無変更で動く。課金ライフサイクル・エラー分類のコードには触れない）。呼び出し:

```php
$ai->complete(
    userId: $lookup->user_id,
    feature: 'meals.label_ocr',
    prompt: PromptTemplate::make('meals.label_ocr.v1', self::SYSTEM_PROMPT, ''),
    tier: 'cheap',
    maxTokens: 300,
    messages: [[
        'role' => 'user',
        'content' => [
            ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $base64]],
            ['type' => 'text', 'text' => 'この栄養成分表示を読み取ってください。'],
        ],
    ]],
);
```

- system プロンプト（完成設計 §3 の方針を具体化）:
  「栄養成分表示の画像から JSON のみを返す。形式 `{"serving_label": string, "per": "serving"|"100g", "kcal": number, "protein_g": number, "fat_g": number, "carb_g": number}`。表示が1食分基準なら per=serving、100g基準なら per=100g。判読できない値は null。画像が栄養成分表示でない・判読不能な場合は `{"error":"unreadable"}` のみを返す。」
- **コスト見積の注意**: `AiCostCalculator::estimateReservation` は `strlen(json)` ベースのため base64 画像で**過大予約**になる（1MB画像 ≒ 1.37M文字 ≒ 34万トークン見積 ≒ $0.34 予約 @haiku）。settle で実額（≒$0.002）に確定するので課金は正しいが、**予約中は月次上限を圧迫**する。対策: クライアント側 canvas 縮小（長辺 ≦1600px, JPEG q0.85 → 通常 ≦800KB）で予約額を実用範囲に抑える。CostCalculator の vision 対応精緻化は本PRの非目標（課金クリティカルコードに触れない）。
- 失敗分類:
  - `QuotaExceededException`（reserve 時） → **リトライ不可**。`failed('ocr_quota_exceeded')` 終端 + 画像削除
  - AI 応答が `{"error":"unreadable"}` → **リトライ不可**。`failed('ocr_unreadable')` 終端 + 画像削除
  - JSON parse 不能 / schema 違反 → **リトライ可**（transient なことが多い）。最終試行で `failed('ocr_invalid_output')` + 画像削除
  - HTTP / 接続失敗 → リトライ可（AiGateway が予約を release 済み）。最終試行で `failed('ocr_provider_error')` + 画像削除
  - `failed()`（timeout / worker kill 安全網）: `ocr_pending` の行を `failed('ocr_provider_error')` へ + 画像削除
- schema 検証（Job 内 pure メソッド）: 必須キー存在 / `per ∈ {serving,100g}` / 数値は null または 0≦kcal≦9999, 0≦macros≦999 / serving_label は null→per から補完（"1食分" / "100g"）。検証後 F1 と同形の result へマップして `found`（source=`label_ocr`）。

### F. 課金・利用量

- AiGateway 経由なので ledger（PR-A）連携は自動: reserve → settle/release、月次スナップショット、利用量バナー反映。追加実装なし。
- `config/ai.php` へ追記不要（tier=cheap = 既存 haiku 単価キーで解決）。
- 上限超過 UX: upload 時 422（メッセージ表示）/ Job 時 `failed('ocr_quota_exceeded')` → モーダルに「AI利用枠を使い切ったため読み取れません」表示。
- テストでは `Http::fake([$this->anthropicFakePattern() => ...])`。`TestCase` の `preventStrayRequests` が外部送信を封じる。gateway の fake クラスは作らない（F1 同様 HTTP レイヤで fake する — 課金ロジックもテストを通ることが価値のため）。

### G. フロント

- 撮影 UI: **`<input type="file" accept="image/*" capture="environment">`**（getUserMedia は使わない）。
  - 理由: 静止画1枚で足り、iOS/Android ともカメラ起動がネイティブで確実。HEIC も iOS が file input 経由では JPEG に自動変換する。F1 のライブスキャンと違い動画ストリームは不要。
- クライアント縮小: canvas で長辺 1600px / JPEG q0.85 に再エンコードしてから upload（コスト・転送量対策。失敗時は原本をそのまま送り、サーバ validate に任せる）。`useLabelImageCapture.ts` composable に切り出し。
- `BarcodeLookupModal.vue` 拡張:
  - Step 追加: `ocr_capture`（撮影/選択 + プレビュー + 送信。lookupId ありなら添付API、なしなら新規API）
  - `not_found` / `failed` 時: エラーメッセージ + 「成分表を撮影して登録」ボタンを scan ステップに表示（lookupId 保持）
  - scan ステップに常設の副ボタン「バーコードがない場合: 成分表を撮影して登録」（入口2）
  - polling: 既存 `pollLookup` の継続条件に `ocr_pending` を追加（間隔は同じ 1.5–2s。OCR は数秒〜十数秒で返る想定）
  - confirm: 既存を再利用。`source === 'label_ocr'` のとき出典表示を「AI読み取り（成分表）· 内容を必ず確認してください」に。per の明示は既存実装（1食分 / 100g あたり）がそのまま効く
  - 文言: miss 時「Open Food Facts に見つかりませんでした。商品の栄養成分表示を撮影すると AI が読み取ります（読み取り結果は必ず確認してから保存されます）」

### H. テスト

§テスト計画参照。

### I. 非目標の再確認

冒頭「非目標」節のとおり。加えて: AiCostCalculator の vision 見積精緻化 / OCR 結果のキャッシュ・共有 / 複数画像 / PDF。

---

## フェーズ分割

| Phase | 内容 | 主な成果物 |
|---|---|---|
| 0 | 本計画書 + handoff の commit/push（実装なし） | docs 2ファイル |
| 0.5 | 設計docsの入口拡張追記（§13.4 / 完成設計§3）+ 本計画書改訂 | docs |
| 1 | barcode nullable migration + Enum ケース追加 + `config/meals.php` + filesystems スタブ + Factory state | migration, `FoodLookupStatus::OcrPending`, factory |
| 2 | Upload API 2本（FormRequest / `StartFoodLabelOcrService` / route / quota） + Feature テスト | Service, route×2, テスト |
| 3 | AiGateway PHPDoc 緩和 + `LookupFoodLabelOcrJob`（プロンプト / schema 検証 / 失敗分類 / 画像削除） + Job テスト | Job + テスト |
| 4 | confirm 経路（barcode=null 対応の `ConfirmFoodLookupService` 修正 + label_ocr source の Feature テスト） | Service修正 + テスト |
| 5 | フロント（`useLabelImageCapture` / モーダル拡張 / polling 拡張 / 文言） | Vue/TS |
| 6 | 画像削除の残り（prune コマンド拡張 + 再アップロード時の旧ファイル削除） + テスト | コマンド拡張 + テスト |
| 7 | Pint / ESLint / types:check / 回帰 / docs 更新 / draft PR | PR |

各 Phase 終了時: テスト実行 → Pint → commit → push → **handoff 更新**（`docs/dev/2026-07-20-pr-f2-label-ocr-handoff.md` を上書き）。

---

## 変更ファイル一覧（予定）

新規:
- `database/migrations/2026_07_20_100001_make_food_lookup_requests_barcode_nullable.php`
- `config/meals.php`
- `app/Http/Requests/FoodLookups/StoreFoodLabelImageRequest.php`
- `app/Services/StartFoodLabelOcrService.php`
- `app/Jobs/LookupFoodLabelOcrJob.php`
- `resources/js/composables/useLabelImageCapture.ts`
- `tests/Feature/FoodLabelImageUploadTest.php`
- `tests/Feature/LookupFoodLabelOcrJobTest.php`

変更:
- `config/filesystems.php`（`food-label-ocr` スタブ disk）
- `app/Enums/FoodLookupStatus.php`（OcrPending 追加）
- `app/Http/Controllers/FoodBarcodeLookupController.php`（storeLabelImage / storeLabelOcr 追加）
- `app/Services/ConfirmFoodLookupService.php`（barcode=null 時は重複判定スキップ）
- `app/Domain/Shared/AI/AiGateway.php`（**PHPDoc 型のみ**緩和）
- `app/Console/Commands/PruneExpiredFoodLookupsCommand.php`（temp 画像削除）
- `database/factories/FoodLookupRequestFactory.php`（notFound / ocrPending state）
- `routes/web.php`（label-image route）
- `resources/js/components/BarcodeLookupModal.vue`
- `tests/Feature/PruneExpiredFoodLookupsCommandTest.php`（画像削除ケース）
- `docs/dev/2026-07-20-pr-f2-label-ocr-handoff.md`（毎Phase更新）

---

## API 契約

### POST /meals/barcode-lookup/{lookupId}/label-image（入口1: 添付）

### POST /meals/label-ocr（入口2: barcodeなし新規。以下の表は共通、404系を除く）

Request: `multipart/form-data` — `image`: JPEG/PNG/WebP ≦5MB, 200–8000px

| 条件 | レスポンス |
|---|---|
| 成功 | `202 {"status":"ocr_pending","lookup_id":"..."}` |
| 他ユーザー / 不存在 | 404 |
| status が not_found/failed 以外（pending 中・found 済 等） | 404（スコープクエリで弾く） |
| 並行アップロードに負けた | `409 {"message":"すでに解析中です。"}` |
| バリデーション不合格 | 422（Laravel 標準） |
| AI 月次上限超過 | `422 {"message":"今月のAI利用枠を使い切りました。..."}` |
| throttle | 429 |

### GET /meals/barcode-lookup/{lookupId}（既存・変更なし）

- `{"status":"ocr_pending"}` が増える（クライアントは pending と同様にポーリング継続）
- OCR 成功: `{"status":"found","source":"label_ocr","result":{...F1と同形...}}`
- OCR 失敗: `{"status":"failed","error_code":"ocr_quota_exceeded|ocr_unreadable|ocr_invalid_output|ocr_provider_error"}`

### POST /meals/barcode-lookup/{lookupId}/confirm（既存・変更なし）

---

## DB / ストレージ

- DB migration: **なし**（status は string カラム、`temp_image_path` 既存）
- Enum: `FoodLookupStatus::OcrPending = 'ocr_pending'`
- 画像: `meals.label_ocr.disk`（default `local`）/ `food-label-ocr/{user_id}/{lookup_id}.{ext}`
- ライフサイクル: upload で作成 → Job 終端 or 再upload or prune で削除。DB 行は F1 同様 expires_at + prune で削除

---

## Job / AiGateway 契約

決定事項E参照。要点:

- AiGateway は **PHPDoc 型緩和のみ**。reserve/settle/release・エラー分類・quota は既存コードのまま通す
- feature=`meals.label_ocr` / tier=`cheap` / maxTokens=300
- 画像は Job 内で disk から読み base64 化（Web リクエスト中に AI 通信しない — §2.11 遵守）
- 終端状態への遷移は条件付きUPDATE（`where('status', OcrPending)`）で stale Job の上書きを防止

---

## フロント画面遷移

```
scan ──submit──▶ polling ──found──▶ confirm ──保存──▶ 閉じる（food-registered emit）
  ▲                │
  │                ├─ not_found/failed（F1）─▶ scan（エラー表示 + [成分表を撮影して登録]）
  │                │                                   │
  │                │                                   ▼
  └────────────────┘                            ocr_capture（撮影/選択 → 縮小 → upload）
                                                       │ 202
                                                       ▼
                                                    polling（ocr_pending を継続対象に）
                                                       ├─ found(label_ocr) ─▶ confirm（出典: AI読み取り）
                                                       └─ failed ─▶ ocr_capture（unreadable は再撮影導線）
                                                                    / scan（quota は終了案内）
```

---

## テスト計画

### Feature: FoodLabelImageUploadTest（Phase 2）
- guest → 401
- 他ユーザーの lookup → 404
- status=pending / found の lookup → 404
- not_found の lookup へ有効画像 → 202 + status=ocr_pending + `Storage::fake` にファイル + `Queue::fake` で Job dispatch
- failed の lookup へも upload 可（再撮影）+ 旧 temp 画像が削除される（Phase 6 で追記）
- MIME 違反（gif/pdf）/ 5MB 超 / 200px 未満 → 422 + ファイル未保存 + status 不変
- 上限超過（ledger を上限まで seed）→ 422 + ファイル未保存
- 二重 upload（1回目で ocr_pending へ遷移済）→ 404 or 409 の系（並行は 409、逐次は 404）

### Feature: LookupFoodLabelOcrJobTest（Phase 3）
- 成功: Http::fake(anthropic) 正常 JSON → found + source=label_ocr + result が F1 同形 + **画像削除済** + `temp_image_path` null + ai_usage_requests が settled
- per=100g / per=serving 両系
- `{"error":"unreadable"}` → failed(ocr_unreadable) + 画像削除 + リトライなし（Http 1回）
- 壊れた JSON → 最終試行後 failed(ocr_invalid_output) + 画像削除
- schema 範囲外（kcal=-5 等）→ invalid 扱い
- quota 超過（ledger seed）→ failed(ocr_quota_exceeded) + **Anthropic へ HTTP されない**（Http::assertNothingSent 相当）
- status ≠ ocr_pending → 何もしない（Http なし）
- AI の結果が自動確定されないこと: Job 成功後も food_items に行が増えない

### Feature: confirm 経路（Phase 4）
- source=label_ocr の found を confirm → food_items 保存 + barcode 紐づけ（既存テストの state 追加）

### Feature: Prune 拡張（Phase 6)
- 期限切れ + temp 画像あり → ファイルも行も削除
- 期限内 → どちらも残る

### 回帰
- F1 の全テスト（BarcodeNormalizer / FoodBarcodeLookup / LookupOpenFoodFactsJob）が無変更で通ること

---

## 受入条件（Given / When / Then）

1. Given F1 で not_found になった lookup、When 成分表画像を upload、Then 202 が返り Job が enqueue され、ポーリングが found(source=label_ocr) に達し、確認フォームに per と読取値が表示される
2. Given 確認フォーム、When ユーザーが値を修正して保存、Then food_items に barcode 付きで保存され、次回同じバーコードのスキャンは照合1で即 hit する
3. Given OCR 終端（found/failed）、Then temp 画像はストレージから消えている
4. Given 月次 AI 上限超過、When upload、Then 422 で画像は保存されない。Job まで進んだ場合も Anthropic へ HTTP されず failed(ocr_quota_exceeded)
5. Given 他ユーザーの lookup、When upload / show / confirm、Then 404
6. Given AI が返した値、When ユーザーが確認しない、Then food_items には決して書かれない

---

## リスクと未決事項

| # | 内容 | 影響 | 提案 |
|---|---|---|---|
| R1 | base64 により予約額が過大（実額 settle は正しい） | 予約中に月次上限を一時圧迫 | クライアント縮小で緩和。CostCalculator の vision 対応は将来PR |
| R2 | デスクトップブラウザでの `capture` 属性はファイル選択になる | UX 差のみ | 許容（選択でも動く） |
| R3 | 本ブランチは PR #143 スタック | #143 マージ前に F2 を main へ出せない | Q1 参照 |
| ~~R4~~ | ~~default キュー未処理の懸念~~ → **解消**: 本番の kioku 文字起こし動作実績で default 処理を確認済み（2026-07-20） | なし | 対応不要 |
| R5 | worker ゼロスケールによる Job 開始遅延（数十秒） | OCR/照合の体感待ち時間増 | ポーリング継続 + 文言で案内。仕様変更なし |

### オーナー判断（全て解決済み 2026-07-20）

- ~~**Q1**~~: **解決** — #143 マージ済み（merge commit `66f4378`）。本ブランチは main へ rebase 済み。
- ~~**Q2**~~: **解決（方針転換）** — バーコードなし直接登録を**本PRに含める**（オーナー承認。決定事項A参照）。
- ~~**Q3**~~: **解決** — 「終端状態で即削除 + 期限切れ prune が安全網」で確定。
- ~~**Q4**~~: **解決** — 本番で文字起こしが動作するとオーナー確認。default キューは処理される。
- **ストレージ補足**: 新バケットは作らず、本番は既存バケットの disk を `MEALS_LABEL_OCR_DISK` で指定（例: `kioku-audio`）。パスプレフィックス `food-label-ocr/` で分離。
