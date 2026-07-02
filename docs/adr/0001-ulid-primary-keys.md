# ADR-0001: 新規ドメインテーブルの主キーに ULID を採用する

- 状態: 承認済み（Phase 1 仕様書 v0.3 で決定。users テーブルとの整合は本 ADR で追記）

## 文脈

ID は URL（Inertia のルート）や将来の Export API レスポンスに露出する。
BIGINT auto increment は件数・順序が推測でき、UUID(v4) は時系列に並ばずインデックス効率も落ちる。

一方、既存の `users` テーブルは Laravel Starter Kit / Fortify 由来で
`id` が BIGINT auto-increment である（`$table->id()`）。passkeys・sessions 等も
この型に依存しているため、`users.id` を ULID へ変更する migration は行わない。

## 決定

- **新規ドメインテーブル**（life_areas, matrix_cells, matrix_cell_items 等）の主キーは ULID とする
- **既存 `users` テーブル**は BIGINT auto-increment のまま維持する
- `users` を参照する `user_id` FK は **bigint unsigned**（`foreignId('user_id')`）とする
- **ドメインテーブル間**の FK（例: life_area_id, matrix_cell_id）は ULID とする
- 新規ドメインテーブルで BIGINT auto increment / UUID を主キーに採用しない

## 理由

- ドメイン ID は URL や API レスポンスに露出しても推測されにくい（ULID の利点）
- 既存認証基盤（Fortify / passkeys / sessions）を壊さない（users は BIGINT 維持）
- Laravel 標準（`HasUlids`）でドメインモデルを扱える

## 影響

- 新規 migration ではドメインテーブルに `ulid('id')->primary()` 相当を使う
- `user_id` には `foreignId('user_id')->constrained()` を使う（bigint）
- ドメインテーブル間 FK は `foreignUlid()` 等で ULID 型に揃える
- Export API の `user.id` は BIGINT として出力する（[export-api.md](../api/export-api.md)）
- 「作成日時降順」はドメイン ULID の辞書順ソートで代替できる
