# キオク Capture Pipeline 移行メモ

- 正本: `docs/design/kioku-canonical-capture-pipeline-v2.md`
- 本番 DB: MySQL 8.4
- テスト: SQLite

## forward migration

1. `memories.raw_kind` / `memories.capture_channel` を nullable で追加
2. `php artisan kioku:capture:backfill-raw-kind --dry-run`
3. `php artisan kioku:capture:backfill-raw-kind`
4. 新規 Capture は両列を必ず埋める
5. 読み取りは新列優先・旧 `source_type` fallback

## ロックリスク

- nullable VARCHAR 追加は MySQL 8.4 では比較的軽量だが、大規模テーブルではオンライン DDL 方針を確認すること
- 本 migration の `down()` は列削除のみ。本番 rollback は前提にせず forward fix を優先する
- migration 実行だけでは外部 AI 呼び出しは発生しない

## source_type 互換マッピング

| source_type | raw_kind | capture_channel |
|---|---|---|
| manual | text | web_text |
| url | url | web_url |
| voice | audio | browser_voice |
| kioku_letter | text | system_generated |
| yoyu / clear_dawn / ai_chat / slack | (監査後) | system_connector |

不明値は推測 backfill しない。
