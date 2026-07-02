# ADR-0001: 主キーに ULID を採用する

- 状態: 承認済み（Phase 1 仕様書 v0.3 で決定）

## 文脈

ID は URL（Inertia のルート）や将来の Export API レスポンスに露出する。
BIGINT auto increment は件数・順序が推測でき、UUID(v4) は時系列に並ばずインデックス効率も落ちる。

## 決定

全テーブルの主キーを ULID で統一する。BIGINT auto increment / UUID は採用しない。

## 理由

- URL や API レスポンスに露出しても推測されにくい
- 時系列順に並びやすく、作成順ソートの代替になる
- Laravel 標準（`HasUlids`）で扱える

## 影響

- 全 migration で `ulid('id')->primary()` 相当の定義を使う
- FK も ULID 型で揃える
- 「作成日時降順」は ULID の辞書順ソートで代替できる
