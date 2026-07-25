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

| 画面 | ルート | 役割 |
|---|---|---|
| ルーティン（実行ハブ・サイドバー着地） | GET /routines | ページ内タブ **今日 / ルーティン / 履歴** + プログラムへの導線。今日のセッション開始が第一ビュー |
| ルーティン編集 / 作成 | GET /routines/create, /routines/{id} | テンプレのステップ編集 |
| 今日/作戦 | GET /today | 作戦カード・チェックイン・プログラム選択（別画面のまま） |
| 履歴 | GET /history | 振り返り（登録しない） |

| 画面 | ルート | 役割 |
|---|---|---|
| 実施項目（整理用・主導線外） | GET /routine-items | ステップで使う項目の一覧整理。普段は不要 |
| S6 プラン詳細 | GET /plans/{id} | 当日調整・セッション開始 |
| S7 実行画面 | GET /sessions/{id} | ブロックログ入力 |

ハブタブ（プログラム / ルーティン / 今日/作戦 / 履歴）は他画面では維持しうるが、**`/routines` の第一構成は今日 / ルーティン / 履歴**。プログラムはサイドバーに加え、`/routines` ヘッダーからも `/programs` へ辿れる。サイドバー自体は変更しない。

### GET /routines の構成（実行ファースト）

ページ内タブは **今日 / ルーティン / 履歴**（この3つが第一構成）。旧ハブタブの縦並びは `/routines` 上では出さない。

1. **今日タブ（既定・第一ビュー）** — 当日のメインセッションを大きく表示し、「セッションを開始」を第一アクションにする。チェックイン・作戦は二次。プランが無ければ空状態（ルーティンタブへ誘導）
2. **ルーティンタブ** — 再利用テンプレ一覧（カテゴリアイコン + 編集 / 今日に追加）。`?tab=routines`（旧 `?tab=menu` も互換）
3. **履歴タブ** — 直近の実行履歴。詳細は `/history`
4. **プログラム導線** — ヘッダー右の「プログラム」リンク → `/programs`（画面・データはそのまま）

- 当日プランは既存 `GetTodayQuery`、作戦サマリは `GetTodayOpsQuery`（いずれも読み取りのみ）
- DB スキーマ・リレーションは変更しない
- サイドバーは変更しない

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

1. **主導線は今日の実行。** サイドバー着地の `/routines` は当日プランの開始・再開を第一に出す。テンプレ編集は「ルーティン」タブ。実施項目の独立画面は整理用で、ハブタブには出さない。
2. **データのつながりを表示。** 実施項目↔ルーティン↔プラン↔履歴の関連を詳細画面に示す。
3. **実行画面は編集させない。** 実績入力に集中。
4. **スナップショット原則（ADR-0006 継続）。** プラン・セッションはテンプレ編集の影響を受けない。
5. **作成は `/routines/create` の下書き画面。** 名前入力後の「保存」で初めて DB に作成する。遷移時点ではレコードを作らない。保存後の再訪や既存編集は「編集」。
6. **サイドバーは変更しない。** 着地 URL・ラベル・共通ナビ内部をいじらない。実行ファーストは `/routines` の中身で実現する。
7. **データ構造は変えない。** 本見直しは画面構成のみ。テーブル追加・列変更は行わない。

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
