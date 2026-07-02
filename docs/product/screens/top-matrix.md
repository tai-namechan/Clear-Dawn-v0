# TOP Matrix 詳細仕様（Phase 1）

Phase 1 仕様書 v0.3 / Phase 1 設計書 v0.3 の内容を正として移植したドキュメント。
v0 の核であり、最初に実装する縦断対象。

## 目的

- 仕事・野球・バイオリン・プライベートなどの領域を俯瞰する
- 「1 ヶ月くらいの間でやるべきこと / 今やるべきこと / 将来どうなっていたいか」の 3 視点で整理する
- 実装対象を絞り、まず毎日使える核を作る

トップページは、ユーザーが「1 ヶ月以内にやるべきこと」「今やるべきこと」「将来どうなっていたいか」
「領域間のバランス」を一目で確認するための画面である。
「今日のフォーカスカード」は置かない。シート自体が思考整理と行動決定の中心だからである。

## Phase 1 スコープ

| 区分 | 内容 |
|---|---|
| 含める | ログイン / TOP マトリックス / セル編集モーダル / 領域管理 / 設定（最低限） |
| 含めない | タスク一覧 / メモ / 振り返り / ルーティン・トレーニング / 動画 / AI / ガントチャート |
| 開発方針 | PC ファースト。スマホ PWA は後続フェーズで本格対応 |
| 運用方針 | 最初は dev / prod のみ。test 環境は後で追加 |

## マトリクス仕様

| 項目 | 仕様 |
|---|---|
| 保持数 | TOP マトリックスはユーザーごとに 1 つだけ。日別・週別・月別管理はしない |
| 横軸 | ユーザーが設定する領域（Life Area）。例: 仕事、野球、バイオリン、プライベート |
| 縦軸 | 固定行。1 ヶ月くらいの間でやるべきこと / 今やるべきこと / 将来どうなっていたいか |
| セル | セルは箱。本文を直接持たず、セル内項目を複数レコードとして持つ |
| チェックボックス | `matrix_rows.is_checkable = true` の行だけ表示。Phase 1 では「今やるべきこと」のみ |
| 履歴 | TOP の履歴は持たない。履歴は後続のメモ・振り返り・ルーティン記録に残す |

## 入出力仕様

| 機能 | 入力 | 出力 |
|---|---|---|
| TOP 表示 | ログインユーザー | 領域、固定行、セル、セル内項目を組み立てたマトリックス表示データ |
| セル項目追加 | matrix_cell_id, title, memo(nullable) | 追加された matrix_cell_item / 更新後の画面 |
| セル項目編集 | matrix_cell_item_id, title, memo(nullable) | 更新された matrix_cell_item |
| 完了切替 | matrix_cell_item_id | is_completed と completed_at の更新結果 |
| セル項目削除 | matrix_cell_item_id | soft delete 後のセル内項目一覧 |
| 領域管理 | name, color, sort_order | life_area の作成・更新・並び替え結果 |

## データ設計

テーブル定義の詳細は [../../data/tables.md](../../data/tables.md) を参照。
Phase 1（M1）は **Matrix 中核 4 テーブル + 横断 activity_logs** で構成する。

| テーブル | 主なカラム | 役割 |
|---|---|---|
| life_areas | id(ULID), user_id(bigint), name, color, sort_order, is_active, deleted_at | 横軸の領域。仕事・野球など |
| matrix_rows | id(ULID), key, label, sort_order, is_checkable | 縦軸の固定行。チェック表示可否も持つ |
| matrix_cells | id(ULID), user_id(bigint), life_area_id, matrix_row_id | 領域 × 行のセル。重複禁止 |
| matrix_cell_items | id(ULID), matrix_cell_id, title, memo, is_completed, completed_at, sort_order, deleted_at | セル内の複数項目。実際の表示単位 |
| activity_logs | id(ULID), user_id(bigint), event_type, subject_type, subject_id, occurred_at | 不変イベントログ。M1 から記録開始 |

### index / unique 制約

| テーブル | 制約・index | 目的 |
|---|---|---|
| life_areas | index(user_id, is_active, sort_order) | ログインユーザーの有効領域を並び順で取得 |
| matrix_rows | unique(key), index(sort_order) | 固定行の重複防止と並び順取得 |
| matrix_cells | unique(user_id, life_area_id, matrix_row_id) | 同じセルの重複防止 |
| matrix_cells | index(user_id), index(life_area_id), index(matrix_row_id) | JOIN・取得の補助 |
| matrix_cell_items | index(matrix_cell_id, sort_order) | セル内項目を並び順で取得 |
| matrix_cell_items | index(matrix_cell_id, is_completed) | 未完了 / 完了の取得補助 |

## Laravel 構成（実装時の作成候補）

| 分類 | 作成候補 | 方針 |
|---|---|---|
| Models | LifeArea, MatrixRow, MatrixCell, MatrixCellItem | ULID を利用。リレーションを明示 |
| Controllers | DashboardController, MatrixCellItemController, LifeAreaController | Controller は薄くする |
| Requests | StoreMatrixCellItemRequest, UpdateMatrixCellItemRequest, StoreLifeAreaRequest 等 | 入力バリデーションを分離 |
| Policies | MatrixCellPolicy, MatrixCellItemPolicy, LifeAreaPolicy | 自分のデータのみ操作可能にする |
| Queries | GetMatrixBoardQuery | 表示用データ取得を集約 |
| Services | AddMatrixCellItemService, ToggleMatrixCellItemCompletionService 等 | 更新系ロジックを Controller から分離 |
| Enums | MatrixRowKey など | 状態・キーの文字列を安全に扱う |

## Vue / Inertia 構成（実装時の作成候補）

| ファイル | 役割 |
|---|---|
| layouts/AppLayout.vue | サイドバー・ヘッダー・背景を共通化（既存を Clear Dawn 仕様に調整） |
| components/layout/Sidebar.vue | ナビゲーション共通化 |
| pages/Dashboard/Index.vue | TOP 画面 |
| pages/Dashboard/Partials/LifeAreaBoard.vue | マトリックス全体 |
| pages/Dashboard/Partials/MatrixCell.vue | 1 つのセル |
| pages/Dashboard/Partials/MatrixCellItem.vue | セル内項目 |
| pages/Dashboard/Partials/MatrixCellEditModal.vue | セル編集モーダル |
| pages/LifeAreas/Index.vue | 領域管理画面 |

## 縦断設計（Route → Controller / Service / Query → Vue）

| 機能 | Route | Controller / Service / Query | Vue |
|---|---|---|---|
| TOP 表示 | GET /dashboard | DashboardController@index → GetMatrixBoardQuery | Dashboard/Index, LifeAreaBoard |
| 項目追加 | POST /matrix-cells/{cell}/items | MatrixCellItemController@store → AddMatrixCellItemService | MatrixCellEditModal |
| 項目編集 | PATCH /matrix-cell-items/{item} | MatrixCellItemController@update → UpdateMatrixCellItemService | MatrixCellEditModal |
| 完了切替 | PATCH /matrix-cell-items/{item}/toggle | MatrixCellItemController@toggle → ToggleMatrixCellItemCompletionService | MatrixCellItem |
| 項目削除 | DELETE /matrix-cell-items/{item} | MatrixCellItemController@destroy → DeleteMatrixCellItemService | MatrixCellEditModal |
| 領域管理 | GET/POST/PATCH/DELETE /life-areas | LifeAreaController + 各 Service | LifeAreas/Index |

## 実装フロー（例: 完了切替）

1. ユーザーがチェックボックスを押す
2. PATCH /matrix-cell-items/{item}/toggle を送信
3. 対象項目が存在しなければ 404
4. 自分のデータでなければ 403（Policy）
5. `is_completed` が false なら true にし、`completed_at = now()`。`activity_logs` に `matrix_item_completed` を記録
6. `is_completed` が true なら false にし、`completed_at = null`。`activity_logs` に `matrix_item_reopened` を記録
7. 保存後、画面を更新する

activity_logs は不変のイベントログである。TOP Matrix のスナップショット履歴ではない。
実行履歴 UI（GET /history）は M3 で実装する。

## 他機能との接続（v0 全体視点）

- 完了切替は M1 から **activity_logs** に記録する（`matrix_item_completed` / `matrix_item_reopened`）。
  実行履歴 UI は M3（[routines.md](./routines.md) 参照）
- セル項目から「メモとして残す」導線を M2 で追加する（[memos.md](./memos.md)）
- 日次振り返りは M2 初期は `completed_at` 参照。将来的に activity_logs 参照へ移行可能
  （[reviews.md](./reviews.md)）

## UI 上の要点（デザインの正: UI 仕様書 v2）

- 中央のマトリクスシートが主役。Excel の表ではなく、上品な紙の整理シートとして仕上げる
- 罫線はかなり薄く、影は柔らかく、シート背景は真っ白ではなく少し生成り
- 「今やるべきこと」行は淡い朝焼け色で柔らかく強調（Sunrise アイコン、SVG、絵文字禁止）
- 詳細なトークン・CSS 仕様は [../../design/design-system.md](../../design/design-system.md) を参照

## 想定データ規模

- 領域: 1 ユーザーあたり数個〜10 個程度
- セル: 領域数 × 3 行（数十件）
- セル内項目: 数百件規模（soft delete 込み）。TOP 表示は 1 クエリ体系（Query 集約）で取得し N+1 を作らない
