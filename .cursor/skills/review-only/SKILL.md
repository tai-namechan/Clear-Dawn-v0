---
name: review-only
description: >-
  コードレビュー専用の思考フレームワーク。
  変更差分（PR・コミット・作業中の diff）の妥当性を確認し、
  提案・根拠・影響範囲・テスト観点のみを出すときに使用する。
  コード変更は一切行わない（No Patch / No Apply）。
---

# Skill: Review Only (No Patch / No Apply)

## 目的

- **コード変更はしない**。提案・根拠・影響範囲・テスト観点のみを出す
- レビュー基準は `architecture-layers.mdc`、共通概念の定義は
  [../_shared/analysis-concepts.md](../_shared/analysis-concepts.md) を参照

## 入力情報

- [対象ファイル/メソッド]
- [変更差分]（PR / コミット / 概要。未指定なら `git diff` で取得する）
- [懸念点]（あれば）
- SEARCH_SCOPE

## 手順

1. **差分の全ファイルを Read する**（diff だけで判断せず、変更後の実ファイルを読む）
2. **Why の整理**: 変更の意図を推測ではなくコード・チケット・コミットメッセージの根拠ベースで整理する
3. **Data Cardinality の追跡**: 変更が扱うデータ件数前提を Repository まで追跡し、変更と整合しているか確認する
4. **Ops Delta の確認**: ループ / Collection 操作 / クエリ数の増減を確認する
5. **影響範囲の特定**: 同じメソッド・コンポーネントを使う他画面・他バッチ・他 API を Grep で洗い出す
6. **レビュー観点の適用**:
   - レイヤー構造（Controller / Service / Repository の責務）
   - FW 機能の重複実装がないか
   - アクセス範囲（所有者・権限等）のスコープ漏れがないか
   - テスト整合性（既存テストが壊れないか、追加テストの要否）
7. **地雷チェック（10項目）** を OK/NG で実施する

## 出力フォーマット（report-output-format.mdc 準拠で1コードブロックに収める）

1. Why（変更理由の妥当性）
2. Data Cardinality（根拠付き）
3. Ops Delta（増減）
4. リスクと影響範囲
5. テスト観点
6. 自己検証（確信度% / 根拠 / 不確実点 / 追加確認）
7. 地雷チェック結果（OK/NG）
