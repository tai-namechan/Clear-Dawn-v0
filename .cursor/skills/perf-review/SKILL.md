---
name: perf-review
description: >-
  パフォーマンスレビュー用の思考フレームワーク。
  SQL 変更・ループ追加・大量データ処理・N+1 の疑いがある変更をレビューし、
  「SQLフィルタ済みをPHPフィルタに置換」等の危険な変更を止めるときに使用する。
  実装・パッチは出さない（提案のみ）。
---

# Skill: Performance Review (Stop Dangerous Changes)

## 目的

- 「SQL フィルタ済みを PHP フィルタに置換」「ループ追加」「N+1 誘発」を実装前に止める
- **実装/パッチは出さない**（提案のみ）
- 数値基準・チャンク分割の詳細は `sql-memory-performance.mdc`、
  共通概念の定義は [../_shared/analysis-concepts.md](../_shared/analysis-concepts.md) を参照

## 入力情報

- [対象メソッド/ファイル]
- [変更内容]（before/after 概要）
- [Query / 取得側のフィルタ条件]
- SEARCH_SCOPE

## 手順

1. **Data Cardinality の確定**: Query / SQL / Eloquent のコードを根拠に、対象データが 0/1・複数・大量のどれか確定する（推測禁止。`first()` / `get()` / WHERE 条件を実コードで確認）
2. **Filter Location の判定**: 絞り込みが SQL（WHERE / JOIN ON）か PHP（Collection）か特定し、変更で SQL → PHP に移動していないか確認する
3. **Ops Delta の具体化**: クエリ発行数・ループ回数・メモリ載せ件数の増減を数で示す
4. **最悪ケースの検証**: 本番最大データ件数（数千件想定）で落ちない/遅くならないことを説明する。破綻する場合はその条件を明示する
5. **安全案の提示**:
   - 安全案A: SQL 側で絞る / メソッド契約の強化（引数で件数を保証する等）
   - 安全案B: 複数件・大量件でも落ちない設計（チャンク分割・ページネーション）

## 出力フォーマット（report-output-format.mdc 準拠で1コードブロックに収める）

1. Data Cardinality（根拠）
2. Filter Location（SQL 優先）
3. Ops Delta（具体的な増減）
4. リスク（最悪ケース）
5. 安全案A/B
6. テスト観点（大量データ / 性能）
