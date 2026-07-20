# PR-F1 バーコード食品検索 — 状況メモ

更新: 2026-07-20 / ブランチ: `cursor/meals-barcode-lookup-a703`（#140 から切り出し）

---

## 0. 概要

設計書 `docs/design/ai-features-implementation-plan.md` §13.3 に基づく
「バーコードスキャン → Open Food Facts 照合 → ユーザー確認 → food_items 保存」フロー。

**バックエンド + フロント骨格 + テスト（Unit / Feature / Job）まで完了。**

任意の後続（`@zxing/browser` / UX 仕上げ）は未着手でよい。

---

## 1. 完了済み

### バックエンド / フロント / ルート
引き継ぎ時点の骨格に加え、以下を完了:

| 項目 | 状態 |
|---|---|
| `LookupOpenFoodFactsJobTest` | 実装済み（成功 / 404 / failed / skip / serving / 100g / 日本語名） |
| `BarcodeNormalizerTest` / `FoodBarcodeLookupTest` | 通過確認済み |
| ESLint（対象3ファイル） | 通過 |
| Wayfinder 再生成 | ローカルで実行（生成物は gitignore 想定） |

### テスト結果（このブランチ）

```bash
php artisan test --compact \
  tests/Unit/BarcodeNormalizerTest.php \
  tests/Feature/FoodBarcodeLookupTest.php \
  tests/Feature/LookupOpenFoodFactsJobTest.php
# 34 passed
```

---

## 2. 任意の後続（本PR必須ではない）

1. `@zxing/browser` fallback（BarcodeDetector 非対応ブラウザ）
2. polling 中キャンセル / confirm 後に食事へ即追加の導線
3. Wayfinder アクションへのフロント移行（現状 `apiFetch` 直呼びで動作）

---

## 3. アーキテクチャ上の注意

- OFF 通信は Queue Job のみ（画面同期通信禁止）
- 外部値は confirm でユーザー確定してから `food_items` 保存
- `Http::fake` は `config('services.openfoodfacts.base_url')` 由来のホストパターンを使う（stray request 禁止）
- `FoodLookupRequest` に BelongsToUser global scope は付けない（Controller で user_id スコープ）
