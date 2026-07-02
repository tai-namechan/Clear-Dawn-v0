# テーブル定義

- **確定**: Phase 1 設計書 v0.3 由来。実装時にこのまま migration に落とす
- **ドラフト**: 後続フェーズの叩き台。各マイルストーン着手前にレビューして確定させる

共通規約（ULID / user_id スコープ / soft delete / 日付）は [conventions.md](./conventions.md) を参照。
全テーブルに `created_at` / `updated_at` を持つ（以下では省略）。

## Phase 1（確定）

### life_areas

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID | FK(users) |
| name | string | 領域名 |
| color | string | パレットキー |
| sort_order | int | 列順 |
| is_active | bool | 非表示フラグ（false で TOP から列が消える） |
| deleted_at | datetime nullable | soft delete（運用の既定は is_active による非表示） |

index: (user_id, is_active, sort_order)

### matrix_rows（グローバルマスタ・seed 投入）

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| key | string | unique。Enum MatrixRowKey に対応 |
| label | string | 表示名 |
| sort_order | int | 行順 |
| is_checkable | bool | チェックボックス表示可否（Phase 1 は「今やるべきこと」のみ true） |

seed する固定 3 行:

| key | label | is_checkable |
|---|---|---|
| monthly | 1 ヶ月くらいの間でやるべきこと | false |
| current | 今やるべきこと | true |
| future | 将来どうなっていたいか | false |

### matrix_cells

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID | FK(users) |
| life_area_id | ULID | FK(life_areas) |
| matrix_row_id | ULID | FK(matrix_rows) |

unique: (user_id, life_area_id, matrix_row_id) / index: user_id, life_area_id, matrix_row_id

### matrix_cell_items

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| matrix_cell_id | ULID | FK(matrix_cells) |
| title | string | 項目名 |
| memo | text nullable | 補足 |
| is_completed | bool | 完了状態 |
| completed_at | datetime nullable | 完了日時 |
| sort_order | int | セル内の並び順 |
| deleted_at | datetime nullable | soft delete |

index: (matrix_cell_id, sort_order), (matrix_cell_id, is_completed)

## Phase 1.5（ドラフト）

### memos

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID | FK(users) |
| life_area_id | ULID nullable | FK(life_areas)。任意タグ |
| matrix_cell_item_id | ULID nullable | FK(matrix_cell_items)。「メモ化」の由来 |
| title | string nullable | |
| body | text | |
| deleted_at | datetime nullable | soft delete |

index: (user_id, created_at), (user_id, life_area_id)

### daily_reviews

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID | FK(users) |
| date | date | 対象日 |
| note | text | 所感 |
| score | tinyint nullable | 自己評価（例: 1-5） |
| next_note | text nullable | 翌日への申し送り |

unique: (user_id, date)。当日完了実績はコピーせずクエリ参照。

### weekly_reviews

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID | FK(users) |
| week_start_date | date | 週の起点（月曜・ISO 週） |
| note | text | 領域バランスの所感 |

unique: (user_id, week_start_date)

## Phase 2（ドラフト）

### routines

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID | FK(users) |
| life_area_id | ULID nullable | FK(life_areas) |
| name | string | |
| frequency_type | string(Enum) | daily / weekly_days |
| frequency_days | json nullable | 曜日指定時の曜日配列 |
| is_active | bool | 無効化フラグ |
| sort_order | int | |
| deleted_at | datetime nullable | |

### routine_steps

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| routine_id | ULID | FK(routines) |
| name | string | 種目名 |
| target_value | string nullable | 目標（例: 10 回 x 3 セット）。構造化は M4 で再検討 |
| video_id | ULID nullable | FK(videos)。参考動画（Phase 3.5 以降） |
| sort_order | int | |
| deleted_at | datetime nullable | |

### routine_logs

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID | FK(users) |
| routine_id | ULID | FK(routines) |
| started_at | datetime | |
| finished_at | datetime nullable | 中断時 null |
| is_completed | bool | 完遂したか |

index: (user_id, started_at)。ログは soft delete しない（不変）。

### routine_step_logs

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| routine_log_id | ULID | FK(routine_logs) |
| routine_step_id | ULID | FK(routine_steps) |
| is_done | bool | |
| value | string nullable | 実績値（回数・重量・時間など）。構造化は M4 で再検討 |

### activity_logs（実行履歴の基盤・Phase 2 前に確定必須）

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID | FK(users) |
| event_type | string(Enum) | matrix_item_completed / routine_completed 等 |
| subject_type | string | ポリモーフィック参照 |
| subject_id | ULID | ポリモーフィック参照 |
| occurred_at | datetime | 発生日時 |

index: (user_id, occurred_at), (user_id, event_type, occurred_at)

## Phase 2.5（ドラフト・スキーマ戦略未確定）

ハイブリッド案（単純数値系は汎用、構造化系は専用）を推奨。M4 着手前に ADR で確定する。

### metrics（マスタ）

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| key | string | unique。weight / sleep 等 |
| label | string | |
| unit | string | kg / 分 等 |

### metric_records（単純数値系: 体重・睡眠）

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID | FK(users) |
| metric_id | ULID | FK(metrics) |
| life_area_id | ULID nullable | FK(life_areas) |
| recorded_on | date | 記録日 |
| value | decimal(8,2) | 数値 |

unique: (user_id, metric_id, recorded_on)

### 筋力・野球（専用テーブル・未設計）

- 筋力: 種目 × セット × 重量 × 回数。routine_step_logs との重複を避ける設計を M4 で確定
- 野球: 打撃 / 投球成績・練習記録。項目定義自体を M4 で確定

## Phase 3〜4（ドラフト）

### finance_categories

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID | FK(users) |
| name | string | |
| type | string(Enum) | income / expense |
| sort_order | int | |
| deleted_at | datetime nullable | |

### finance_entries

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID | FK(users) |
| finance_category_id | ULID | FK(finance_categories) |
| life_area_id | ULID nullable | FK(life_areas) |
| type | string(Enum) | income / expense |
| amount | int | 円。小数は使わない |
| date | date | |
| memo | string nullable | |
| deleted_at | datetime nullable | |

index: (user_id, date), (user_id, finance_category_id)

### videos

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID | FK(users) |
| life_area_id | ULID nullable | FK(life_areas) |
| title | string | |
| storage_key | string | Object Storage 上のキー。公開 URL は保存しない |
| duration_seconds | int | 上限 60 秒目安 |
| size_bytes | bigint | 上限 50MB 目安 |
| deleted_at | datetime nullable | soft delete。実体削除は非同期 |

### ai_suggestions（保存するか自体が未決定）

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | ULID | FK(users) |
| kind | string(Enum) | 提案種別 |
| content | json | 提案内容 |
| status | string(Enum) | pending / adopted / dismissed |

M7 着手前に保存要否・保持期間を確定する。
