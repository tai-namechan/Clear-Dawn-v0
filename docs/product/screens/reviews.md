# 日次・週次振り返り仕様

対象フェーズ: Phase 1.5（Milestone M2）

## 目的

- 日次振り返り: 1 日の締め。行動と気づきを記録し、翌日への申し送りを作る
- 週次振り返り: 週単位で領域バランスを確認し、TOP Matrix の見直しにつなげる

TOP Matrix は履歴を持たないため、「その日・その週に何をしたか」の参照はここで行う。

## 画面

| 画面 | ルート | 概要 |
|---|---|---|
| 振り返り一覧 | GET /reviews | 日次・週次の一覧（カレンダー / リスト） |
| 日次振り返り | GET /reviews/daily/{date} | その日の振り返り閲覧・入力 |
| 週次振り返り | GET /reviews/weekly/{week} | その週の振り返り閲覧・入力 |

- `{date}` は `YYYY-MM-DD`、`{week}` は ISO 週 `YYYY-Www`（例 `2026-W27`）
- 1 日 1 件 / 1 週 1 件。既存があれば編集モードで開く

## 日次振り返りの構成

| セクション | 内容 | データ（M2 初期） | 将来 |
|---|---|---|---|
| 当日の完了実績 | 当日完了した「今やるべきこと」セル項目の一覧 | `completed_at` が当日の項目をクエリ参照 | activity_logs（`matrix_item_completed`）参照へ移行可能 |
| 所感 | 自由記述 | daily_reviews.note | — |
| 評価 | その日の自己評価（例: 5 段階） | daily_reviews.score(nullable) | — |
| 翌日への申し送り | 自由記述 | daily_reviews.next_note(nullable) | — |

### completed_at 参照の注意

M2 初期は `completed_at` で当日完了を判定するが、完了後に取り消すと振り返り表示から消える。
M1 から記録されている **activity_logs**（不変イベントログ）を参照すれば、
取り消し後も「その日に完了した事実」が残る。M2 以降の改善で activity_logs 参照へ移行する。

## 週次振り返りの構成

| セクション | 内容 | データ |
|---|---|---|
| 週の完了実績サマリ | 領域別の完了件数・実行履歴サマリ | 集計クエリで参照表示（activity_logs ベースを推奨） |
| 記録サマリ | 体重・睡眠など計測記録の週間推移（M4 以降で表示追加） | 参照表示 |
| 領域バランスの所感 | 自由記述 | weekly_reviews.note |

## 主要操作

| 操作 | 入力 | 挙動 |
|---|---|---|
| 日次作成・更新 | date, note, score(nullable), next_note(nullable) | upsert（unique(user_id, date)） |
| 週次作成・更新 | week 起点日, note | upsert（unique(user_id, 週起点日)） |
| 過去分の閲覧 | date / week | 読み取り専用でない（後からの追記を許容） |

## 設計方針

- **完了実績はコピーしない**。振り返りレコードは所感などの入力値のみを持ち、
  実績は表示時にクエリで参照する（データ二重化と不整合を避ける）
- 週の起点は月曜（ISO 週）とする
- タイムゾーンは Asia/Tokyo 固定
- 想定件数: 日次 365 件/年、週次 52 件/年。一覧はページネーションまたは月単位表示

## データ（ドラフト）

テーブル定義は [../../data/tables.md](../../data/tables.md) の
`daily_reviews` / `weekly_reviews` を参照。

## 認可

- 自分の振り返りのみ閲覧・編集可能（Policy）
