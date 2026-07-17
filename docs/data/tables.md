# テーブル定義

- **確定**: Phase 1 設計書 v0.3 由来。実装時にこのまま migration に落とす
- **ドラフト**: 後続フェーズの叩き台。各マイルストーン着手前にレビューして確定させる

共通規約（ULID / user_id スコープ / soft delete / 日付）は [conventions.md](./conventions.md) を参照。
全テーブルに `created_at` / `updated_at` を持つ（以下では省略）。

**ID 型の方針**: 新規ドメインテーブルの主キーは ULID。`users` を参照する `user_id` は
bigint unsigned（既存 `users.id` に合わせる）。ドメインテーブル間の FK は ULID。

## Phase 1（確定）

Matrix の中核 4 テーブル（life_areas / matrix_rows / matrix_cells / matrix_cell_items）に加え、
横断イベントログ **activity_logs** を M1 で作成・記録開始する。

### life_areas

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | bigint unsigned | FK(users)。既存 `users.id`（BIGINT auto-increment）に合わせる |
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
| user_id | bigint unsigned | FK(users)。既存 `users.id`（BIGINT auto-increment）に合わせる |
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

### activity_logs（横断イベントログ・M1 で確定）

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | bigint unsigned | FK(users)。既存 `users.id`（BIGINT auto-increment）に合わせる |
| event_type | string(Enum) | M1: `matrix_item_completed` / `matrix_item_reopened`。M3 以降: `routine_completed` 等を追加 |
| subject_type | string | ポリモーフィック参照 |
| subject_id | ULID | ポリモーフィック参照 |
| occurred_at | datetime | 発生日時 |

index: (user_id, occurred_at), (user_id, event_type, occurred_at)

- **不変のイベントログ**。更新・削除しない。完了取り消しは `matrix_item_reopened` を追加する
- TOP Matrix 自体のスナップショット履歴ではない（[ADR-0003](../adr/0003-single-top-matrix-without-history.md)）
- 実行履歴 UI（GET /history）は **M3** で実装。M1 では記録のみ

## Phase 1.5（ドラフト）

### memos

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | bigint unsigned | FK(users)。既存 `users.id`（BIGINT auto-increment）に合わせる |
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
| user_id | bigint unsigned | FK(users)。既存 `users.id`（BIGINT auto-increment）に合わせる |
| date | date | 対象日 |
| note | text | 所感 |
| score | tinyint nullable | 自己評価（例: 1-5） |
| next_note | text nullable | 翌日への申し送り |

unique: (user_id, date)。当日完了実績はコピーせずクエリ参照。

### weekly_reviews

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | bigint unsigned | FK(users)。既存 `users.id`（BIGINT auto-increment）に合わせる |
| week_start_date | date | 週の起点（月曜・ISO 週） |
| note | text | 領域バランスの所感 |

unique: (user_id, week_start_date)

## Phase 2（ドラフト）

### routines

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | bigint unsigned | FK(users)。既存 `users.id`（BIGINT auto-increment）に合わせる |
| life_area_id | ULID nullable | FK(life_areas) |
| name | string | |
| frequency_type | string(Enum) | daily / weekly_days |
| frequency_days | json nullable | 曜日指定時の曜日配列 |
| is_active | bool | 無効化フラグ |
| sort_order | int | |
| deleted_at | datetime nullable | |

### routine_steps

> **実装の正は ADR-0007 系マイグレーション。** 下記は旧ドラフト。現行は `routine_item_id` / `title` nullable / `video_id` / 汎用 target 列。

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
| user_id | bigint unsigned | FK(users)。既存 `users.id`（BIGINT auto-increment）に合わせる |
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

## Phase 2.5（確定: メトリクス + 食事）

ハイブリッド案を採用（[ADR-0008](../adr/0008-condition-records-hybrid-schema.md)）。
単純数値系は汎用、食事は専用テーブル。

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
| user_id | bigint unsigned | FK(users)。既存 `users.id`（BIGINT auto-increment）に合わせる |
| metric_id | ULID | FK(metrics) |
| life_area_id | ULID nullable | FK(life_areas) |
| recorded_on | date | 記録日 |
| value | decimal(8,2) | 数値 |

unique: (user_id, metric_id, recorded_on)

### 筋力・野球（専用テーブル・未設計）

- 筋力: 種目 × セット × 重量 × 回数。routine_step_logs との重複を避ける設計を M4 で確定
- 野球: 打撃 / 投球成績・練習記録。項目定義自体を M4 で確定

## Phase 2.5 追加: 食事記録（確定・M4b）

スナップショット方針は [ADR-0009](../adr/0009-meal-records-snapshot.md)。
画面仕様は [meals.md](../product/screens/meals.md)。

### food_items（マイ食品マスタ）

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | bigint unsigned | FK(users) |
| name | string | 食品名 |
| serving_label | string | 1 サービングの表示（例: 1杯、1個） |
| kcal | decimal(8,2) | 1 サービングあたり |
| protein_g | decimal(8,2) | 1 サービングあたり |
| fat_g | decimal(8,2) | 1 サービングあたり |
| carb_g | decimal(8,2) | 1 サービングあたり |
| deleted_at | datetime nullable | soft delete |

index: (user_id, name)

### meal_entries（食事エントリ・スナップショット）

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | bigint unsigned | FK(users) |
| food_item_id | ULID nullable | FK(food_items)。直接入力時 null。集計には使わない |
| eaten_on | date | 摂取日 |
| meal_type | string(Enum) | breakfast / lunch / dinner / snack |
| name | string | 表示名スナップショット |
| quantity | decimal(8,2) | サービング倍率 |
| kcal | decimal(8,2) | スナップショット（確定値） |
| protein_g | decimal(8,2) | スナップショット |
| fat_g | decimal(8,2) | スナップショット |
| carb_g | decimal(8,2) | スナップショット |
| note | string nullable | |

index: (user_id, eaten_on)。unique なし（1 日複数エントリ可）。物理削除。

### nutrition_goals（栄養目標）

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | bigint unsigned | FK(users)。**unique** |
| kcal | decimal(8,2) | 日次目標 |
| protein_g | decimal(8,2) | |
| fat_g | decimal(8,2) | |
| carb_g | decimal(8,2) | |

## キオク（実装済み + クイックキャプチャ差分）

仕様は [kioku-quick-capture.md](../product/kioku-quick-capture.md) を参照。

### memories（実装済み。★がクイックキャプチャで追加）

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | bigint unsigned | FK(users) |
| source_type | string | manual / url / voice★ / yoyu / clear_dawn ほか。text へ rename しない |
| memory_type | string nullable | AI 分類結果 |
| title | string | AI 生成。保存直後は「整理中…」 |
| raw_content | text **nullable★** | manual/url の canonical raw。**voice では null**（原音声が canonical raw） |
| transcript_text★ | text nullable | 音声からの派生テキスト。再生成可能 |
| summary | text nullable | AI 要約 |
| structured_data | json nullable | AI 構造化結果 |
| tags | json nullable | |
| captured_at | timestamp | 入力完了時刻 |
| importance | tinyint | default 3 |
| sensitive | bool | Recall／表出除外のみ。enrich では外部 AI へ送信される（現行仕様維持） |
| status | string | captured / enriching / ready / failed / archived（全 source_type 共通の総合ライフサイクル） |
| transcription_status★ | string nullable | voice のみ: pending / processing / ready / failed。manual/url は null |
| client_capture_id★ | uuid nullable | 端末生成。**(user_id, client_capture_id) unique** で再送を冪等化 |
| referenced_count | int | |
| last_referenced_at☆ | timestamp nullable | 手紙の初回開封時に表示項目へ記録（liveのみ。[kioku-final-remaining-implementation.md](../product/kioku-final-remaining-implementation.md) §11.1） |
| last_delivered_at☆ | timestamp nullable | live手紙の published/empty 確定時。未読でも翌日再送を防ぐ（[kioku-concierge-daily-pilot.md](../product/kioku-concierge-daily-pilot.md)） |

★=クイックキャプチャ、☆=コンシェルジュ手紙で追加。source_type にはコンシェルジュ評価ログ用の `kioku_letter` が加わる（Letter 候補からは除外）。

不変条件: raw_content は作成後変更不可（Model updating ガード。修復時は `$memory->permitRawContentRepair()`）。

### memory_assets（新規）

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| memory_id | ULID | FK(memories) cascade |
| kind | string | `audio_original`（voice の canonical raw） |
| disk | string | `KIOKU_AUDIO_DISK`（非公開 disk。本番は `kioku-audio`。公開 URL は保存しない） |
| path | string | 非公開オブジェクトキー |
| mime_type | string | サーバー側検証済みの実形式 |
| byte_size | bigint | 上限 20MB（config） |
| duration_ms | int nullable | クライアント申告の録音長。上限 3 分（config）。実音声解析は未実施 |
| checksum | string nullable | sha256 |

再生は所有者認可付き stream（`GET /kioku/memories/{memory}/audio`）経由のみ。HTTP Range/206 は未対応（先頭再生が MVP）。

storage cleanup:

- Eloquent で Memory を削除 → Asset 経由で storage 実体も削除
- アカウント削除（Profile）→ `CleanupUserKiokuAudioService` が User 削除前に disk/path と `kioku-audio/{userId}/` を掃除。失敗時は削除中断。その後 FK cascade で DB 行削除
- DB cascade のみでは storage は残る（通常フローでは使わない）

### kioku_capture_events（新規・計測）

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | bigint unsigned | FK(users) |
| event | string | capture_started / local_saved / local_save_failed / server_synced / sync_failed |
| source_type | string | manual / voice |
| duration_ms | int nullable | capture 開始→端末保存 |
| retry_count | int nullable | 同期リトライ回数 |

**raw 本文・transcript・音声内容は保存しない。**

### kioku_letters（コンシェルジュ手紙）

仕様は [kioku-final-remaining-implementation.md](../product/kioku-final-remaining-implementation.md) §11 と [kioku-concierge-daily-pilot.md](../product/kioku-concierge-daily-pilot.md) を参照。

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | bigint unsigned | FK(users) |
| week_start | date | 対象週の月曜日（dailyでも delivery_date の週開始を保存） |
| mode | string | `live` / `test`。default `live` |
| cadence | string | `daily` / `weekly`。default `weekly` |
| delivery_date | date | ユーザーtimezone上の配信対象日 |
| dedupe_key | string | live: `daily:YYYY-MM-DD` / `weekly:YYYY-MM-DD`。test: `test:{ulid}`。**(user_id, dedupe_key) unique** |
| pilot_day | tinyint nullable | 1〜14（daily pilot） |
| status | string | generating / published / empty / failed / opened / evaluating / evaluated / halted |
| character_variant | string | shiori / nagi。作成後不変 |
| intro | text nullable | AI 生成の導入（最大2文） |
| context | text nullable | 手動で渡す今週の文脈 |
| candidate_count | int | AI へ渡した候補数 |
| item_count | tinyint | 0〜5（daily は最大2） |
| prompt_key | string | `kioku.concierge.letter.v1` |
| model | string nullable | 実際に使ったモデル |
| generation_meta | json nullable | AI usage request ID 等。failures 履歴を append。raw 本文は入れない |
| retry_count | unsignedTinyInteger | failed 明示再試行回数。default 0 |
| generated_at / published_at | timestamp nullable | 生成・公開日時 |
| opened_at / completed_at | timestamp nullable | 初回開封・評価完了 |
| halted_at / halt_resolved_at | timestamp nullable | sensitive halt |
| halt_resolution_note | text nullable | resolve-halt の確認内容 |
| test_expires_at | timestamp nullable | test letter の失効 |
| evaluation_memory_id | ULID nullable | FK(memories) null on delete。評価 Memory（testは原則作らない） |

index: (user_id, status, published_at)、(user_id, mode, cadence, delivery_date)

### kioku_letter_items（手紙の項目）

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| letter_id | ULID | FK(kioku_letters) cascade |
| memory_id | ULID | FK(memories) cascade。元 Memory |
| position | tinyint | 1〜5（dailyは1〜2）。**(letter_id, position) unique** |
| title_snapshot | string | 生成時タイトル |
| summary_snapshot | text nullable | 生成時要約 |
| headline | string | 手紙見出し（最大60文字） |
| why_now | string | なぜ今か（最大180文字） |
| related_memory_ids | json nullable | 最大2件 |
| verdict | string nullable | hit / soft_hit / miss / sensitive_leak |
| verdict_note | text nullable | 任意500文字以内 |
| verdict_at | timestamp nullable | 判定日時 |

**(letter_id, memory_id) unique**。DB enum は使わず、定数＋validation で固定する。

### kioku_concierge_schedules（日次pilot状態）

仕様は [kioku-concierge-daily-pilot.md](../product/kioku-concierge-daily-pilot.md)。対象ユーザーを env へ直書きしない。

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | bigint unsigned | FK(users)。**unique** |
| state | string | inactive / active / paused / halted / completed |
| pilot_start_date / pilot_end_date | date | 開始コマンド引数で記録 |
| pilot_days | unsignedTinyInteger | default 14 |
| timezone | string | default `Asia/Tokyo` |
| daily_delivery_time | string | default `21:00`（H:i） |
| next_delivery_at | timestamp nullable | timezone換算の次回due |
| consecutive_unopened | unsignedTinyInteger | default 0 |
| pause_reason | text nullable | |
| timestamps | | |

## Phase 3〜4（ドラフト）

### finance_categories

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | bigint unsigned | FK(users)。既存 `users.id`（BIGINT auto-increment）に合わせる |
| name | string | |
| type | string(Enum) | income / expense |
| sort_order | int | |
| deleted_at | datetime nullable | |

### finance_entries

| カラム | 型 | 備考 |
|---|---|---|
| id | ULID | PK |
| user_id | bigint unsigned | FK(users)。既存 `users.id`（BIGINT auto-increment）に合わせる |
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
| user_id | bigint unsigned | FK(users)。既存 `users.id`（BIGINT auto-increment）に合わせる |
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
| user_id | bigint unsigned | FK(users)。既存 `users.id`（BIGINT auto-increment）に合わせる |
| kind | string(Enum) | 提案種別 |
| content | json | 提案内容 |
| status | string(Enum) | pending / adopted / dismissed |

M7 着手前に保存要否・保持期間を確定する。

## セルフマネジメントOS拡張（2026-07-16 確定 / ADR-0010・0011）

列の詳細は各 migration を正とする。ここでは所属と役割のみ列挙する。全テーブル ULID PK・user_id スコープ。

### Phase 1: 目標・プログラム

| テーブル | 役割 |
|---|---|
| goals / goal_metrics / goal_change_logs | 階層目標・達成指標（metrics 参照）・理由つき変更履歴。matrix_cell への片方向参照のみ |
| programs / program_versions | プログラムと版（コピーオンライト。旧版・実行済み記録は不変） |
| program_phases / program_weeks | フェーズ（intent: base/deload/intensify/taper/test）と週 |
| program_day_templates / program_day_steps / program_step_items | DAY テンプレート（曜日固定 or 順番制・優先度3段）→ STEP（step_kind 9種）→ 種目処方（1RM比・RPE・左右・テンポ・必須度・代替） |
| program_week_item_prescriptions | 週×処方のメインリフト重量表（percent_of_reference が正。個人1RMは personal_profile_entries） |
| program_choice_groups / program_choice_options | 選択式メニュー（例: 水曜） |
| program_constraints / program_metric_targets / program_attachments | 配置制約等の program_rule / 指標目標 / 添付（PDF等） |
| personal_profile_entries | 個人プロファイル（key×value×有効日。1RM・既往・安全方針等。値は import コマンドで投入・リポジトリ非収載） |
| user_module_settings | モジュール有効/無効（module_key × enabled） |
| metrics 拡張 | nullable user_id・description_plain・measurement_method・is_advanced 追加 |

### Phase 2: 実行連携（新テーブルなし・nullable 列追加のみ）

routine_plans（program_version_id / program_week_id / program_day_template_id / generation_source / choice_option_id / choice_reason / repeat_reason）、
routine_plan_steps（program_step_item_id / step_kind / required_level）、
routine_sessions（session_rpe）、routine_session_steps（status_reason / pain_score / pain_location）、
routine_block_logs（rpe / distance_value / duration_seconds / side / extra json）、
routine_items（resource_weights json / neural_demand / throw_type / flags / plain_description）

### Phase 3: コンディション・食事

daily_checkins（日次一意・Hooper系0-10・部位別張り json）/ symptom_observations（部位×種別×重症度 — H7/S13 の入力）/
measurement_sources / personal_baselines（再計算可能キャッシュ）/ daily_resource_states（resource_key 別 EWMA・z_load・relStrain + readiness）/
nutrition_target_profiles（期間・フェーズ別栄養目標。既存 nutrition_goals は初期値としてコピー後フォールバック）/
metric_records 拡張（source_id / is_estimated / reliability / corrected_from_id）

### Phase 4: ルール・推奨・判断

rule_definitions（kind: evidence_rule/clinician_rule/user_policy/program_rule/ai_suggestion。params json・根拠・対象・限界・確信度・版）/
rule_evaluations（日次評価・入力スナップショット）/ recommendations + recommendation_options（scope=承認3段 A/B/C・差分・確信度・不足データ）/
recommendation_decisions（選択・理由）/ outcome_evaluations（事後評価）

### Phase 5 以降（未実装・ドラフト）

weekly_reports / kioku_learning_exports / kioku_recall_references
