# ルーティン実行システム仕様

対象フェーズ: Phase 2（Milestone M3）— Routine System 転換後

## 目的

習慣・練習・学習を共通の「ルーティン実行」フローで管理する。

```
実施項目（部品ライブラリ）
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
| 部品（下位） | S4/S5 | GET /routine-items | 整理用ライブラリ |

| 画面 | ルート | 役割 |
|---|---|---|
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

1. **主導線はルーティン作成。** 実施項目はルーティン編集からインライン作成できる。独立画面は整理用。
2. **データのつながりを表示。** 実施項目↔ルーティン↔プラン↔履歴の関連を詳細画面に示す。
3. **実行画面は編集させない。** 実績入力に集中。
4. **スナップショット原則（ADR-0006 継続）。** プラン・セッションはテンプレ編集の影響を受けない。

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

## データ

テーブル: `routine_items`, `routines`, `routine_steps`, `routine_plans`, `routine_plan_steps`, `routine_sessions`, `routine_session_steps`, `routine_block_logs`

詳細は [routine-system-redesign.md](./routine-system-redesign.md) および [ADR-0007](../adr/0007-routine-system-conversion.md)。

## 認可

自分のデータのみ操作可能（Policy、ADR-0002 継続）。
