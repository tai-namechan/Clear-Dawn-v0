# ルーティン実行システム仕様

対象フェーズ: Phase 2（Milestone M3）— Routine System 転換後

## 目的

習慣・練習・学習を共通の「ルーティン実行」フローで管理する。

```
実施項目（ステップで使う項目）
  ↓ 組み合わせる
ルーティン（再利用テンプレート）
  ↓ 日付を付けて生成
今日の実行プラン（当日版スナップショット）
  ↓ 開始
実行セッション（進行中〜完了）
  ↓ 蓄積
ブロックログ（不変の事実ログ）
```

## 画面とルート

| タブ | 画面 | ルート | 役割 |
|---|---|---|---|
| ルーティン（主導線） | S2/S3 | GET /routines, /routines/{id} | ルーティン作成・ステップ追加 |
| 今日やる | S1 | GET /today | 当日開始・再開のみ |
| 履歴 | S8 | GET /history | 振り返り（登録しない） |

| 画面 | ルート | 役割 |
|---|---|---|
| 実施項目（整理用・主導線外） | GET /routine-items | ステップで使う項目の一覧整理。普段は不要 |
| S6 プラン詳細 | GET /plans/{id} | 当日調整・セッション開始 |
| S7 実行画面 | GET /sessions/{id} | ブロックログ入力 |

## API（主要）

| 操作 | ルート |
|---|---|
| プラン作成 | POST /plans |
| プラン編集・削除 | PATCH/DELETE /plans/{p} |
| プランステップ | POST/PATCH/DELETE /plans/{p}/steps, PATCH reorder |
| セッション開始 | POST /plans/{p}/sessions |
| セッション表示・完了・中断 | GET/POST /sessions/{s}/complete・abort |
| ブロックログ | POST /session-steps/{ss}/blocks, PATCH/DELETE /blocks/{bl} |
| 実施項目 CRUD | /routine-items |
| ルーティン CRUD | /routines（変更なし） |

## 設計原則

1. **主導線はルーティン作成。** 「ステップを追加」でその場にやることを作れる。実施項目の独立画面は整理用で、ハブタブには出さない。
2. **データのつながりを表示。** 実施項目↔ルーティン↔プラン↔履歴の関連を詳細画面に示す。
3. **実行画面は編集させない。** 実績入力に集中。
4. **スナップショット原則（ADR-0006 継続）。** プラン・セッションはテンプレ編集の影響を受けない。
5. **作成は `/routines/create` の下書き画面。** 名前入力後の「保存」で初めて DB に作成する。遷移時点ではレコードを作らない。保存後の再訪や既存編集は「編集」。

## 実施項目名とステップ名

| 概念 | 列 | 例 |
|---|---|---|
| 実施項目名（カタログ） | `routine_items.name` | WGS / カノン Aパート / AWS IAM章 / スクワット |
| ステップ名（ルーティン内表示） | `routine_steps.title` nullable | ゆっくり確認 / 権限まわりを復習 |

表示ルール: `step.title ?? item.name`（Resource の `display_name`）。

プラン生成時は `title` をスナップショットし、セッション開始時の `item_name` は解決済み表示名を固定する。

## 動画の解決順

| 優先 | 列 | 例（実施項目: スクワット） |
|---|---|---|
| 1 | `routine_steps.video_id` | ルーティンA: 通常スクワット動画 / B: 投手用 / C: リハビリ用 |
| 2 | `routine_items.default_video_id` | 項目の既定見本 |

プラン・セッション生成時に解決済み `video_id` をスナップショットする。

## ステータス

- ルーティンから生成（ステップ≥1）→ 最初から `ready`
- 空プラン → `draft`、ステップ1件追加で自動 `ready`
- 「準備完了にする」ボタンは廃止

## 記録値（汎用列）

| 概念 | 列 |
|---|---|
| 負荷 | target_load + load_unit |
| 量 | target_amount + amount_unit |
| ブロック数 | target_blocks |
| 休憩 | rest_seconds |

記録方式（TrackingType）は UI 駆動。DB列は共通。

単位 UI: プリセット（ページ / 問 / 小節 / BPM / レベル / 点 / 回 等）+「その他」自由入力。DB は string のまま。

## データ

テーブル: `routine_items`, `routines`, `routine_steps`, `routine_plans`, `routine_plan_steps`, `routine_sessions`, `routine_session_steps`, `routine_block_logs`

詳細は [routine-system-redesign.md](./routine-system-redesign.md) および [ADR-0007](../adr/0007-routine-system-conversion.md)。

## 認可

自分のデータのみ操作可能（Policy、ADR-0002 継続）。
