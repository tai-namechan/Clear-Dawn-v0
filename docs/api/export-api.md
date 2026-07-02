# Export API 仕様（v1 移行用）

対象フェーズ: Phase 4.5 / Milestone M8（設計自体は M1 時点から意識する）

## 目的

v0 に蓄積したドメインデータを JSON でエクスポートし、将来の v1 へ移行できるようにする。
v0 は実運用アプリであり、v1 で構成が変わってもデータを失わないための Export 契約を定義する。

## 基本方針

- **allowlist 方式を正とする**: モジュールごとに ExportResource または DTO を実装し、
  **明示したフィールドだけ**を出力する
- **禁止**: DB 全カラム出力 / Model の `toArray()` 素通し / `$hidden` 依存 /
  denylist だけで秘密情報を消す設計
- 将来テーブルにカラムを追加しても、ExportResource を更新しない限り Export に自動露出しない
- **スキーマバージョニング**: トップレベルに `schema_version`（semver）を持つ
- **モジュール単位出力**: 全件一括に加え、モジュール単位でも出力可能
- 動画はメタデータのみ出力する（実体ファイルの移行は別途手順を設計する）

## 提供形態

| 形態 | 内容 | 認証 |
|---|---|---|
| 画面からのダウンロード | GET /settings/export から JSON ファイルをダウンロード | セッション認証 + **password.confirm 推奨** |
| API エンドポイント | v1 移行バッチが叩く想定（M8） | read-only scope トークン + rate limit + 監査ログ（検討対象） |

認証方式の package 構成（Sanctum 等）は M8 前に確定し ADR 化する。

## JSON トップレベル契約

| フィールド | 型 | 説明 |
|---|---|---|
| schema_version | string | semver（例: `"0.1.0"`）。テーブル構造変更時に上げ、変更履歴を本ドキュメントに追記 |
| source | string | 固定値 `"clear-dawn-v0"`。v1 import 時の名前空間 |
| exported_at | string | ISO 8601（Asia/Tokyo オフセット付き） |
| export_scope | string | `"full"` またはモジュール名（`"matrix"` 等） |
| user | object | allowlist 済みユーザー識別情報（下記） |
| modules | object | モジュールごとの counts + データ |

### user（allowlist）

以下 **4 フィールドのみ** 出力する。

| フィールド | 型 | 説明 |
|---|---|---|
| id | integer | 既存 `users.id`（BIGINT） |
| name | string | 表示名 |
| email | string | v1 アカウント突合用。個人情報である旨を画面に明示 |
| created_at | string | ISO 8601 |

**含めない**: password hash / remember_token / two_factor_secret /
two_factor_recovery_codes / email_verified_at / passkey・credential 情報 /
認証・検証トークン一切

### modules の構造

各モジュールは以下の形とする。

```json
"matrix": {
    "counts": {
        "life_areas": 4,
        "matrix_rows": 3,
        "matrix_cells": 12,
        "matrix_cell_items": 28,
        "activity_logs": 15
    },
    "life_areas": [ ... ],
    "matrix_rows": [ ... ],
    "matrix_cells": [ ... ],
    "matrix_cell_items": [ ... ],
    "activity_logs": [ ... ]
}
```

モジュール一覧:

| モジュール | 含むテーブル / データ |
|---|---|
| matrix | life_areas, matrix_rows（key 含む）, matrix_cells, matrix_cell_items, activity_logs |
| memos | memos |
| reviews | daily_reviews, weekly_reviews |
| routines | routines, routine_steps, routine_logs, routine_step_logs, activity_logs（routine 系イベント） |
| records | metrics（key 含む）, metric_records |
| finance | finance_categories, finance_entries |
| videos | videos（メタデータのみ。storage_key は allowlist に含めるが署名付き URL は出さない） |

- 各レコードは **ExportResource / DTO が allowlist したフィールドのみ** を持つ
- **soft delete 済みレコードも出力する**。`deleted_at` を allowlist に含め、v1 側で取捨選択できるようにする
- グローバルマスタ（matrix_rows, metrics）は ULID に加え **`key`** を必ず含め、v1 側は key で突合できるようにする

## 絶対に Export に含めないデータ

以下は ExportResource に最初から存在させない（denylist フィルタで消すのではなく、書かない）。

| 区分 | 具体例 |
|---|---|
| 認証情報 | password hash, remember_token, two_factor_secret, two_factor_recovery_codes |
| トークン | passkey / credential, session, password reset token, email verification token, Sanctum / API / OAuth token |
| インフラ秘密 | Deploy Hook, Sentry DSN, DB 接続情報, 環境変数 |
| 一時 URL / 認証 | 署名付き URL, Object Storage 認証情報 |
| 内部運用 | jobs, cache, sessions, password_reset_tokens 等の FW 内部テーブル |
| 内部ログ | アプリ内部運用ログ（activity_logs はユーザーの事実ログであり Export 対象） |

## v1 Import の冪等性

- v1 側は **`(source, record ULID)`** を一意キーとして upsert する
  - 例: `("clear-dawn-v0", "01HXYZ...")` → 同一レコードの再 import は上書き更新（冪等）
- `user.id`（BIGINT）は v1 アカウント突合用。ドメインレコードの安定キーは ULID
- グローバルマスタは ULID ではなく **`key`**（monthly / current / future, weight / sleep 等）で突合
- `schema_version` / `exported_at` / モジュール別 `counts` で import 後の件数検証を行う

## 大量データ時の出力方針

- 基本: モジュール内テーブルごとに **`chunkById`**（ULID 昇順）で読み、**ストリーミング出力**
- 全件をメモリに載せない
- **閾値超**（件数は M8 設計時に確定。目安: 総レコード 5 万件超）:
  Queue で Export ジョブを生成 → 完了後に生成済みファイルをダウンロード
- 部分失敗時は途中ファイルを破棄し、成功した完全なファイルのみ提供する
- 出力対象は必ず `user_id = 認証ユーザー` でスコープ（他ユーザーのデータを含めない）

## セキュリティ

- 認証必須・HTTPS 必須
- 画面 Export は **password.confirm** を推奨（本人操作の再確認）
- API Export 時は read-only scope、rate limit、監査ログを検討対象とする
- エクスポートファイルの取り扱いはユーザー責任。画面上に注意書きを表示する
- 本リポジトリ（public）のドキュメント・テストに実在の個人情報・秘密情報を書かない

## 実装時のテスト観点

| # | 検証内容 |
|---|---|
| 1 | 出力 JSON を再帰走査し、禁止キー（password, remember_token, two_factor_secret, token, secret 等）が **どの階層にも存在しない** こと |
| 2 | 他ユーザーのデータが含まれないこと（他ユーザーのレコードを事前作成し不在を確認） |
| 3 | soft delete 済みレコードが `deleted_at` 付きで含まれること |
| 4 | モジュール別 `counts` と実件数が一致すること |
| 5 | 未認証 401 / 権限外 403 |
| 6 | 大量データ時に chunk が効くこと（メモリ・クエリ数） |
| 7 | `schema_version` / `source` / `exported_at` / `export_scope` の存在 |
| 8 | ExportResource に未登録の新カラムが自動で出力されないこと（カラム追加時の回帰テスト） |
| 9 | `user` が allowlist 4 フィールドのみであること |

## schema_version 変更履歴

| バージョン | 変更内容 |
|---|---|
| 0.1.0 | 初版。allowlist 方式、matrix / memos / reviews / routines / records / finance / videos モジュール |
