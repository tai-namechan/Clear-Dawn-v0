# PR-F1 バーコード食品検索 — Cursor 引き継ぎメモ

作成: 2026-07-20 / ブランチ: `claude/review-pr-136-138-4cq6um` / コミット: `98a5f4d`

---

## 0. 概要

設計書 `docs/design/ai-features-implementation-plan.md` §13.3 に基づく「バーコードスキャン → Open Food Facts 照合 → ユーザー確認 → food_items 保存」フローの PR-F1 実装。
**バックエンド + フロントエンド骨格 + テスト骨格** まで完了。残りを以下に列挙する。

---

## 1. 完了済み（コード済み・コミット済み）

### バックエンド

| ファイル | 内容 |
|---|---|
| `app/Enums/FoodLookupStatus.php` | Pending / Found / NotFound / Failed enum |
| `app/Support/BarcodeNormalizer.php` | EAN-8 / UPC-A / EAN-13 検証 + GS1 チェックディジット + UPC-A→EAN-13 正規化 |
| `app/Models/FoodLookupRequest.php` | 照合リクエストモデル（casts: status enum, result array, expires_at datetime） |
| `database/factories/FoodLookupRequestFactory.php` | `found()` state 付き factory |
| `database/migrations/2026_07_19_100001_...` | food_items に barcode/barcode_type 追加 + food_lookup_requests テーブル作成 |
| `app/Models/FoodItem.php` | barcode / barcode_type を fillable + PHPDoc に追加 |
| `app/Services/StartFoodBarcodeLookupService.php` | scan → own food_items hit? → miss なら lookup request 作成 → job dispatch |
| `app/Services/ConfirmFoodLookupService.php` | 確認後 food_items 保存（soft-deleted 同一バーコードは restore + 上書き） |
| `app/Jobs/LookupOpenFoodFactsJob.php` | OFF API v2 照合（ShouldBeUnique, 3 tries, backoff, serving/100g 判定） |
| `app/Http/Controllers/FoodBarcodeLookupController.php` | store / show / confirm の 3 アクション |
| `app/Http/Requests/FoodLookups/StoreFoodBarcodeLookupRequest.php` | barcode validation |
| `app/Http/Requests/FoodLookups/ConfirmFoodLookupRequest.php` | 栄養値 validation（StoreFoodItemRequest 準拠） |
| `config/services.php` | `openfoodfacts.base_url` / `openfoodfacts.user_agent` 追加 |
| `routes/web.php` | 3 ルート追加（meals.barcode-lookup.store / show / confirm） |

### フロントエンド

| ファイル | 内容 |
|---|---|
| `resources/js/composables/useBarcodeScan.ts` | Native BarcodeDetector API でカメラスキャン（非対応ブラウザは手動入力へフォールバック） |
| `resources/js/components/BarcodeLookupModal.vue` | 4 ステップモーダル: scan → polling → confirm → hit |
| `resources/js/pages/Meals/Index.vue` | 「写真で記録」ボタンを「バーコード」に差し替え + BarcodeLookupModal 統合 |

### テスト骨格

| ファイル | 内容 |
|---|---|
| `tests/Unit/BarcodeNormalizerTest.php` | EAN-13 / EAN-8 / UPC-A / 不正入力 / 実在バーコード DataProvider |
| `tests/Feature/FoodBarcodeLookupTest.php` | store/show/confirm の全パス + 所有権スコープ + soft-delete 復元 + 連打再利用 |
| `tests/Feature/LookupOpenFoodFactsJobTest.php` | **空ファイル（要実装）** |

---

## 2. 残作業

### 2-A. テスト実装と修正（最優先）

1. **`tests/Unit/BarcodeNormalizerTest.php`** — `php artisan test --compact tests/Unit/BarcodeNormalizerTest.php` を実行して全テスト通過を確認
2. **`tests/Feature/FoodBarcodeLookupTest.php`** — `php artisan test --compact tests/Feature/FoodBarcodeLookupTest.php` を実行。失敗するテストがあれば修正
3. **`tests/Feature/LookupOpenFoodFactsJobTest.php`** — 中身を実装する。以下のケースが必要:

```
- test_job_updates_lookup_to_found_on_success
  Http::fake で OFF 200 レスポンスを返し、lookup の status が Found、result に name/kcal 等が入ることを検証
- test_job_updates_lookup_to_not_found_on_404
  Http::fake で 404 を返し、status が NotFound になること
- test_job_marks_failed_after_retries_exhausted
  job->failed() を直接呼び、status が Failed、error_code が provider_error になること
- test_job_skips_non_pending_lookup
  status が Found のレコードを渡し、HTTP 呼び出しが行われないことを assertNotSent
- test_job_prefers_serving_over_100g
  nutriments に _serving 値がある場合 per=serving が返ること
- test_job_falls_back_to_100g_when_no_serving
  _serving が欠落時に per=100g が返ること
- test_job_uses_japanese_product_name_first
  product_name_ja があればそちらが name に使われること
```

### 2-B. フロントエンド仕上げ

1. **`@zxing/browser` fallback（任意・後回しでもよい）**
   - 設計 §13.1 では BarcodeDetector 非対応ブラウザ向けに `@zxing/browser` の dynamic import を指定
   - 現状は手動入力フォールバックのみ。必要なら `npm install @zxing/browser` して `useBarcodeScan.ts` に dynamic import 分岐を追加
   - bundle サイズを `npm run build` で確認すること

2. **`BarcodeLookupModal.vue` の UX 改善（任意）**
   - エラー時に「もう一度スキャン」ボタンで scan ステップに戻る導線がある（step.value = 'scan' のみ）
   - polling 中のキャンセルボタン追加を検討
   - confirm 後の食品を即座に食事記録に追加する導線（現在は food-registered イベントで通知のみ）

3. **Wayfinder 再生成**
   - ルート追加したので `php artisan wayfinder:generate` を実行して `resources/js/actions/` を更新
   - ただし今の実装は `apiFetch` 直呼びなので Wayfinder アクションへの移行は任意

### 2-C. ESLint / TypeScript チェック

```bash
npx eslint resources/js/composables/useBarcodeScan.ts resources/js/components/BarcodeLookupModal.vue resources/js/pages/Meals/Index.vue
npx vue-tsc --noEmit
```

エラーがあれば修正。特に `useBarcodeScan.ts` の `declare global` と `BarcodeDetector` の型定義に注意。

### 2-D. Pint 再実行

PHP ファイルに変更を加えたら必ず `vendor/bin/pint --dirty --format agent` を実行。

---

## 3. アーキテクチャ上の注意事項

### 設計原則（`docs/design/ai-features-implementation-plan.md` §13）

- **Open Food Facts への通信は画面リクエスト中に行わない** — 必ず Queue Job 経由
- **AI/外部データを自動確定しない** — confirm 画面でユーザーが値を編集してから food_items に保存
- **`per serving` と `per 100g` を明示** — confirm 画面で出典（source）と基準（per）を表示
- **負数・異常上限・小数を validate** — ConfirmFoodLookupRequest で min:0 / max:9999 / max:999

### 所有権スコープ

- `FoodBarcodeLookupController::show` と `confirm` では `where('user_id', $request->user()->id)` でスコープ済み
- `FoodLookupRequest` には BelongsToUser global scope は**付いていない**（意図的 — FoodItem と異なるライフサイクル）
- テストで他ユーザーのリクエストへのアクセスが 404 になることを検証済み

### Job の冪等性

- `LookupOpenFoodFactsJob` は `ShouldBeUnique`（`uniqueFor=300`, `uniqueId=lookupRequestId`）
- `handle()` 冒頭で `status !== Pending` なら early return
- `failed()` は `where('status', Pending)` ガード付きで update

### soft-delete 復元

- `ConfirmFoodLookupService` は `unique(user_id, barcode)` 制約のため、soft-deleted な同一バーコードの食品を `withTrashed` で検索し、あれば restore + fill + save

---

## 4. 全体のテスト実行コマンド

```bash
# PR-F1 関連テストのみ
php artisan test --compact tests/Unit/BarcodeNormalizerTest.php tests/Feature/FoodBarcodeLookupTest.php tests/Feature/LookupOpenFoodFactsJobTest.php

# 食事記録全体の回帰テスト
php artisan test --compact tests/Feature/MealEntryTest.php tests/Feature/FoodBarcodeLookupTest.php

# 全テスト（最終確認）
php artisan test --compact
```

---

## 5. ファイル一覧（変更・追加）

```
app/Enums/FoodLookupStatus.php                      [新規]
app/Http/Controllers/FoodBarcodeLookupController.php [新規]
app/Http/Requests/FoodLookups/ConfirmFoodLookupRequest.php      [新規]
app/Http/Requests/FoodLookups/StoreFoodBarcodeLookupRequest.php [新規]
app/Jobs/LookupOpenFoodFactsJob.php                  [新規]
app/Models/FoodItem.php                              [変更: barcode/barcode_type 追加]
app/Models/FoodLookupRequest.php                     [新規]
app/Services/ConfirmFoodLookupService.php             [新規]
app/Services/StartFoodBarcodeLookupService.php        [新規]
app/Support/BarcodeNormalizer.php                     [新規]
config/services.php                                  [変更: openfoodfacts 追加]
database/factories/FoodLookupRequestFactory.php       [新規]
database/migrations/2026_07_19_100001_...             [新規]
resources/js/components/BarcodeLookupModal.vue        [新規]
resources/js/composables/useBarcodeScan.ts            [新規]
resources/js/pages/Meals/Index.vue                   [変更: バーコードボタン + モーダル統合]
routes/web.php                                       [変更: 3ルート追加]
tests/Unit/BarcodeNormalizerTest.php                  [新規]
tests/Feature/FoodBarcodeLookupTest.php               [新規]
tests/Feature/LookupOpenFoodFactsJobTest.php          [新規・中身なし]
```
