# データ設計規約

全テーブル・全モジュール共通の規約。個別テーブルは [tables.md](./tables.md) を参照。

## 主キー: ULID

- 各テーブルの主キーは ULID で統一する（[ADR-0001](../adr/0001-ulid-primary-keys.md)）
- 理由: URL や API レスポンスに ID が露出しても推測されにくく、時系列順に並びやすい
- BIGINT auto increment は採用しない。UUID ではなく ULID を優先する

## user_id スコープ

- 全ユーザーデータは `user_id` で所有者を限定する（[ADR-0002](../adr/0002-user-scoped-single-user-domain.md)）
- 取得クエリの WHERE に必ず `user_id` 条件を含める。Policy で認可を二重に守る
- 例外はグローバルマスタ（`matrix_rows` / `metrics`）のみ
- `Admin` / `Shop` / `tenant_id` などマルチテナント概念は持ち込まない

## soft delete 方針

| 区分 | 方針 | 対象例 |
|---|---|---|
| ユーザー入力データ | soft delete（`deleted_at`） | matrix_cell_items, memos, routines, videos, finance_entries |
| 事実ログ | 削除しない（不変） | routine_logs, activity_logs |
| 単純記録 | 物理削除でよい（入力ミス訂正優先） | metric_records |
| 領域（life_areas） | 削除より `is_active = false`（非表示）を既定とする | - |

## 日付・タイムゾーン

- タイムゾーンは Asia/Tokyo 固定
- 日次データ（記録・振り返り・収支）は `date` 型カラムで持つ。datetime にしない
- 「当日」の判定は datetime（例: `completed_at`）を JST で日付化して行う
- 週の起点は月曜（ISO 週）。URL 表現は `YYYY-Www`

## Enum

- 状態・種別の文字列は PHP の Enum（backed enum）で管理する（例: MatrixRowKey）
- DB には文字列で保存し、アプリ層で Enum に変換する
- 重複定義しない。既存の Enum を優先して使う

## sort_order

- 並び替え可能なリソース（領域・セル内項目・種目など）は int の `sort_order` を持つ
- 採番はサーバー側（Service）で行い、クライアントから任意値を受け取らない

## 金額

- 金額は int（円）で保持する。decimal / float は使わない

## タイムスタンプ

- 全テーブルに `created_at` / `updated_at` を持つ（Eloquent の timestamps に任せる。手動代入しない）

## Export 前提

- v1 移行のため、全テーブルのデータは Export API で完全出力できる形を保つ
  （[../api/export-api.md](../api/export-api.md)）
- スキーマ変更時は Export スキーマの `schema_version` を上げる

## パフォーマンス基準

- 1000 件以上になり得るデータの全件取得は避ける（chunk / cursor / ページネーション）
- 一覧・グラフは期間指定 + 集計クエリを基本とする
- WHERE / JOIN の条件はインデックスを利用できる形にする（各テーブルの index 定義参照）
- リレーションは Eager Load（`with()`）し、N+1 を作らない
