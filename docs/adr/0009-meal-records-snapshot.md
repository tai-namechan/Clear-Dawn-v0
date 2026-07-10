# ADR-0009: 食事記録の栄養スナップショット

- 状態: 承認済み（M4b）

## 文脈

食事エントリはマイ食品マスタ（`food_items`）を参照して追加できる。
マスタの栄養値を後から変更・削除した場合、過去の食事記録の合計 kcal / PFC が
遡及的に変わると、日次サマリや推移グラフの意味が壊れる。

## 決定

`meal_entries` は作成・更新時点の栄養値を **スナップショットとして保持**する。

| 項目 | 方針 |
|---|---|
| 保持カラム | `name`, `kcal`, `protein_g`, `fat_g`, `carb_g`（＋ `quantity`） |
| マスタ選択時 | サーバー側で「1 サービング値 × quantity」を計算して確定 |
| 直接入力時 | 受け取った栄養値をそのまま確定 |
| food_item_id | nullable。参照用。集計には使わない |
| food_items 変更後 | 既存 meal_entries の値は変わらない |
| food_items 削除後 | soft delete。既存 meal_entries は残る（food_item_id は残してもよい） |
| meal_entries 削除 | 物理削除（入力ミス訂正優先） |

集計（日次合計・区分小計・推移）は常に `meal_entries` のスナップショットカラムを SUM する。

## 理由

1. 過去の「その日に食べたもの」の事実を保全する
2. マスタ編集・削除が履歴集計に波及しない
3. ルーティン系のスナップショット方針（ADR-0006）と整合する

## 影響

- Create/UpdateMealEntryService がスナップショット確定の単一責務を持つ
- Feature テストで「マスタ変更後も既存エントリが不変」を固定する
- マイ食品の栄養値変更は「これから追加するエントリ」にのみ効く

## 関連

- [meals.md](../product/screens/meals.md)
- [ADR-0006](./0006-training-system-plan-run-snapshot.md)
- [ADR-0008](./0008-condition-records-hybrid-schema.md)
- [tables.md](../data/tables.md)
