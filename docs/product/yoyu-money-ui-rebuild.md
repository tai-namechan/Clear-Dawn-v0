# ヨユウ「お金の余裕」UI再設計メモ

- 日付: 2026-07-21
- ADR: [ADR-0013](../adr/0013-yoyu-money-ui-information-architecture.md)
- 設計正: [yoyu-money-margin-design.md](./yoyu-money-margin-design.md) セクション6

## 目的

家計簿UIではなく、余裕を作る意思決定支援として画面を再構成する。

把握 → 気づく → 比較する → 判断する → 実行する → 振り返る

## ナビ対応

| 旧 | 新 |
|---|---|
| ダッシュボード | ホーム |
| 入出金 | 今月 |
| 口座・カード・ローン | 資産・返済（内部タブ） |
| 明細・CSV | 明細（内部タブ） |
| 分析・シミュレーター・判断履歴 | 計画（内部タブ、「見直したこと」） |
| 設定 | 歯車 |

## 実装メモ

- Presenter相当のデータは `MoneyProjectionQuery` / `MoneySetupProgressService` で整形
- フロント共通: `resources/js/components/yoyu-money/*`
- 金額は minor 文字列 + BigInt 表示（浮動小数禁止）
- Vitest は未導入のため Node test でナビIAを検証
