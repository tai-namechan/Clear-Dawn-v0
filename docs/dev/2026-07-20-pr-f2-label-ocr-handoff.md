# PR-F2 成分表OCR — 残作業 handoff

更新: 2026-07-20 / ブランチ: `feature/meals-label-ocr-f2`（**main ベース**。#143 マージ済み）
正本: `docs/dev/2026-07-20-pr-f2-label-ocr-implementation-plan.md`（先に読むこと。入口拡張版に改訂済み）

## 状態

- 最新コミット: このファイルと同一コミット（`git log --oneline -1` で確認)
- **Phase 2 まで完了。次は Phase 3（OCR Job 本実装）**

## Phase チェックリスト

- [x] Phase 0: 実装計画書 + handoff の作成・push
- [x] Phase 0.5: 設計docs（§13.4 / 完成設計§3）へ入口拡張追記 + 計画書改訂
- [x] Phase 1: barcode nullable migration / `FoodLookupStatus::OcrPending` / `config/meals.php` / filesystems `food-label-ocr` スタブ / Factory state（notFound / ocrPending / withoutBarcode）— F1回帰 38 passed
- [x] Phase 2: Upload API 2本 — `StoreFoodLabelImageRequest` / `StartFoodLabelOcrService` / controller 2 action / route（throttle:10,1）/ quota 事前チェック / `FoodLabelImageUploadTest` 9 passed。**注: `LookupFoodLabelOcrJob` は dispatch 契約のみの骨格（handle 空実装）**
- [ ] Phase 3: `AiGateway` PHPDoc 緩和（型のみ）/ `LookupFoodLabelOcrJob@handle` 本実装（vision呼び出し・schema検証・失敗分類・画像削除）/ `tests/Feature/LookupFoodLabelOcrJobTest.php`
- [ ] Phase 4: `ConfirmFoodLookupService` barcode=null 対応 + source=label_ocr の Feature テスト
- [ ] Phase 5: フロント — `useLabelImageCapture.ts` / `BarcodeLookupModal.vue` に `ocr_capture` ステップ（入口1+2）+ polling へ `ocr_pending` 追加
- [ ] Phase 6: `PruneExpiredFoodLookupsCommand` の temp 画像削除 + テスト
- [ ] Phase 7: Pint / ESLint / types:check / F1 回帰 / docs 更新 / draft PR 作成

## 次にやる具体タスク（Phase 3）

1. `AiGateway::complete` の `$messages` PHPDoc を `list<array{role: string, content: string|list<array<string, mixed>>}>` に緩和（**型のみ。ロジック変更禁止**）
2. `LookupFoodLabelOcrJob@handle` 実装: status===OcrPending ガード → disk から画像読込+base64 → `$ai->complete(feature:'meals.label_ocr', tier:'cheap', maxTokens:300, messages:[image+text blocks])` → JSON parse → schema 検証（per∈{serving,100g} / kcal 0-9999 / macros 0-999）→ F1同形 result で found(source=label_ocr) or failed(error_code)。終端で画像削除+temp_image_path null 化。失敗分類は計画書E参照（quota/unreadable は即終端、他はリトライ）
3. `tests/Feature/LookupFoodLabelOcrJobTest.php`（計画書テスト計画参照。`$this->anthropicFakePattern()` 使用、`config(['ai.anthropic.api_key' => 'test-key'])` 必要）
4. テスト → Pint → commit/push → 本ファイル更新

## 未決事項（オーナー回答待ち）

- Q1: #143 を先にマージ → 本ブランチを main へ rebase してよいか
- Q2: バーコードなし登録は範囲外でよいか（推奨: 範囲外）
- Q3: 画像破棄タイミングは「終端状態で即削除 + prune 安全網」でよいか（推奨: この解釈）
- ~~Q4~~: 解決済み — 本番で文字起こし動作をオーナー確認。default キューは処理される

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
