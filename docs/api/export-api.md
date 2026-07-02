# Export API 仕様（v1 移行用）

対象フェーズ: Phase 4.5（設計自体は M1 時点から意識する）

## 目的

v0 に蓄積した全データを JSON でエクスポートし、将来の v1 へ移行できるようにする。
v0 は「捨てるプロトタイプ」ではないが、v1 で構成が変わってもデータを失わないための保険とする。

## 基本方針

- **完全出力を正とする**: v1 側のインポート仕様は未定のため、
  「全カラム + メタ情報」をそのまま出力する（v1 側で変換する前提）
- **スキーマバージョニング**: 出力 JSON はトップレベルに `schema_version` を持つ。
  テーブル構造の変更時にバージョンを上げ、変更内容を本ドキュメントに追記する
- **モジュール単位で出力可能**: 全件一括に加え、モジュール（matrix / memos / reviews /
  routines / records / finance / videos）単位でも出力できる
- 動画はメタデータのみ出力する（実体ファイルの移行は別途手順を設計する）

## 提供形態

| 形態 | 内容 | 状態 |
|---|---|---|
| 画面からのダウンロード | /settings/export から JSON ファイルをダウンロード | v0 で提供 |
| API エンドポイント | トークン認証による取得（v1 の移行バッチが叩く想定） | 認証方式が **未決定**（M8 前に確定） |

認証方式（Sanctum トークン等）は package 構成の確認を含めて M8 前に確定し、ADR 化する。

## 出力フォーマット（案）

```json
{
    "schema_version": "v0.1",
    "exported_at": "2026-01-01T00:00:00+09:00",
    "user": { "id": 1, "name": "...", "email": "..." },
    "modules": {
        "matrix": {
            "life_areas": [],
            "matrix_rows": [],
            "matrix_cells": [],
            "matrix_cell_items": []
        },
        "memos": { "memos": [] },
        "reviews": { "daily_reviews": [], "weekly_reviews": [] },
        "routines": {
            "routines": [],
            "routine_steps": [],
            "routine_logs": [],
            "routine_step_logs": [],
            "activity_logs": []
        },
        "records": { "metrics": [], "metric_records": [] },
        "finance": { "finance_categories": [], "finance_entries": [] },
        "videos": { "videos": [] }
    }
}
```

- 各レコードは DB カラムをそのまま出力する（ULID、タイムスタンプ、soft delete 済みを含む）
- soft delete 済みレコードも `deleted_at` 付きで出力する（v1 側で取捨選択する）

## 実装方針（M8 時点で具体化）

- 出力対象は必ず `user_id = 認証ユーザー` でスコープする（他ユーザーのデータを含めない）
- 件数が多いテーブルは chunk で読み出しながらストリーミング出力する（全件メモリ展開を避ける）
- Export 処理の Feature テストで「他ユーザーのデータが含まれないこと」を必ずアサートする

## セキュリティ

- 出力にはメールアドレス等の個人情報が含まれるため、認証必須・HTTPS 必須
- エクスポートファイルの取り扱いはユーザー責任だが、画面上に注意書きを表示する
- トークン認証を採用する場合、トークンはハッシュ保存・失効操作を提供する
