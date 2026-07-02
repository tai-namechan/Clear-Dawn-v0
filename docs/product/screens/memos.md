# メモ仕様

対象フェーズ: Phase 1.5

## 目的

思考ログを蓄積する。TOP Matrix が「今の状態の整理」を担うのに対し、
メモは「考えたことの記録」を担う。TOP に履歴を持たせない方針の受け皿でもある。

## 画面

| 画面 | ルート | 概要 |
|---|---|---|
| メモ一覧・編集 | GET /memos | 一覧 + インライン / モーダルでの作成・編集 |

一覧と編集を 1 画面に収め、画面遷移を最小にする（詳細ページは v0 では作らない）。

## 主要操作

| 操作 | 入力 | 挙動 |
|---|---|---|
| 作成 | body, life_area_id(nullable), title(nullable) | メモを作成 |
| 編集 | body, life_area_id, title | 上書き更新（版管理はしない） |
| 削除 | - | soft delete |
| 領域で絞り込み | life_area_id | 一覧をフィルタ |
| 検索 | キーワード | title / body の部分一致 |

## TOP Matrix との接続

- セル項目の編集モーダルに「メモとして残す」導線を追加する（Phase 1.5 で実装）
  - セル項目の title / memo を初期値にしたメモ作成フォームを開く
  - 参照 FK（`matrix_cell_item_id`）は nullable で持ち、由来をたどれるようにする
- メモは `life_area_id` を任意タグとして持つ（領域なしメモも許容）

## 設計方針

- 本文はプレーンテキスト + 改行を基本とする。Markdown 対応は未決定（v0 では非対応を推奨）
- 想定件数: 年間数千件級。一覧はページネーション必須、検索はインデックスを意識する
- 並び順は作成日時の降順を既定とする（ULID 順で代替可能）

## データ（ドラフト）

テーブル定義は [../../data/tables.md](../../data/tables.md) の `memos` を参照。
主なカラム: id(ULID), user_id, life_area_id(nullable), matrix_cell_item_id(nullable),
title(nullable), body, deleted_at。

## 認可

- MemoPolicy で自分のメモのみ操作可能にする
