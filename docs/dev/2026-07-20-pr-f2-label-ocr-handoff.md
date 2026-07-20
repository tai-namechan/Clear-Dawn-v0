# PR-F2 成分表OCR — 残作業 handoff

更新: 2026-07-20 / ブランチ: `feature/meals-label-ocr-f2`（PR #143 `cursor/meals-barcode-lookup-a703` 上にスタック）
正本: `docs/dev/2026-07-20-pr-f2-label-ocr-implementation-plan.md`（先に読むこと）

## 状態

- 最新コミット: このファイルと同一コミット（`git log --oneline -1` で確認）
- **Phase 0 完了（設計書 push 済み）。実装は未着手 — オーナーの続行指示待ち**

## Phase チェックリスト

- [x] Phase 0: 実装計画書 + handoff の作成・push
- [ ] Phase 1: `FoodLookupStatus::OcrPending` 追加 / `config/meals.php` / Factory state（`notFound()` / `ocrPending()`）
- [ ] Phase 2: Upload API — `StoreFoodLabelImageRequest` / `FoodBarcodeLookupController@storeLabelImage` / route（throttle:10,1）/ quota 事前チェック / `tests/Feature/FoodLabelImageUploadTest.php`
- [ ] Phase 3: `AiGateway` PHPDoc 緩和（型のみ）/ `app/Jobs/LookupFoodLabelOcrJob.php` / `tests/Feature/LookupFoodLabelOcrJobTest.php`
- [ ] Phase 4: confirm 経路の Feature テスト追加（source=label_ocr）
- [ ] Phase 5: フロント — `useLabelImageCapture.ts` / `BarcodeLookupModal.vue` に `ocr_capture` ステップ + polling へ `ocr_pending` 追加
- [ ] Phase 6: `PruneExpiredFoodLookupsCommand` の temp 画像削除 + 再アップロード時の旧ファイル削除 + テスト
- [ ] Phase 7: Pint / ESLint / types:check / F1 回帰 / docs 更新 / draft PR 作成

## 次にやる具体タスク（Phase 1）

1. `app/Enums/FoodLookupStatus.php` に `case OcrPending = 'ocr_pending';`
2. `config/meals.php` 新規: `['label_ocr' => ['disk' => env('MEALS_LABEL_OCR_DISK', 'local')]]`
3. `database/factories/FoodLookupRequestFactory.php` に `notFound()` / `ocrPending()`（temp_image_path 付き）state
4. `php artisan test --compact tests/Feature/FoodBarcodeLookupTest.php` で回帰確認 → Pint → commit/push → 本ファイル更新

## 未決事項（オーナー回答待ち）

- Q1: #143 を先にマージ → 本ブランチを main へ rebase してよいか
- Q2: バーコードなし登録は範囲外でよいか（推奨: 範囲外）
- Q3: 画像破棄タイミングは「終端状態で即削除 + prune 安全網」でよいか（推奨: この解釈）
- Q4: Managed queue「integrations」が default キューも処理するか（Laravel Cloud 設定画面で確認。No なら F1 の Job も本番で動かない）

## 本番構成メモ（2026-07-20 スクリーンショット確認）

- Web と Queue worker は別コンテナ → temp 画像は本番でバケット必須（filesystems に `food-label-ocr` スタブ + `MEALS_LABEL_OCR_DISK` env。デプロイ前にバケット作成）
- Scheduler 有効（prune は動く）/ worker 0-1 ゼロスケール（Job 開始に数十秒かかりうる）
- 既存バケット: videos（default）/ kioku-audio

## 詰まりそうな点・注意

- **AiGateway は課金クリティカル**。PHPDoc の messages 型緩和以外、絶対に触らない（reserve/settle/release のロジック変更禁止）
- テストは `Http::fake([$this->anthropicFakePattern() => ...])` を使う（`tests/TestCase.php` 参照）。実 HTTP は preventStrayRequests で落ちる
- コスト予約が base64 で過大になる件は計画書 R1 参照（設計済み・対応不要）
- 環境制約: このリポジトリの検証環境では `npm run build` がフォント CDN 遮断で失敗する（既存事象）。`types:check` / `test:js` / ESLint で代替
- 既存の全体テストで 3 件 fail するのは環境制約（S3 region 1件 / bcmath 2件）で無関係

## ローカル検証コマンド

```bash
php artisan test --compact \
  tests/Feature/FoodBarcodeLookupTest.php \
  tests/Feature/LookupOpenFoodFactsJobTest.php \
  tests/Feature/PruneExpiredFoodLookupsCommandTest.php \
  tests/Unit/BarcodeNormalizerTest.php
vendor/bin/pint --dirty --format agent
npm run types:check && npm run test:js
npx eslint resources/js/components/BarcodeLookupModal.vue resources/js/composables/useLabelImageCapture.ts
```
