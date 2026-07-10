# 食事記録仕様

対象フェーズ: Phase 2.5（M4b）

## 目的

その日に食べたものを記録し、カロリーと PFC（たんぱく質・脂質・炭水化物）の
日次合計・区分小計・目標達成率・推移を確認する。

## 画面

| 画面 | ルート | 概要 |
|---|---|---|
| 食事記録 | GET /meals | 日付ナビ + 日次サマリ + 区分別エントリ + 推移 |
| マイ食品 | GET /meals/foods | ユーザー固有の食品マスタ CRUD |

サイドバーの「パフォーマンス管理」は `/records` ハブへ誘導する。
ハブから食事記録（`/meals`）とコンディション管理（`/records/condition`）へ分岐する。

## 食事記録画面の構成

1. **DateNavigator**（既存コンポーネント再利用）— `?date=`（既定: 当日）
2. **日次サマリカード**
   - 合計 kcal
   - PFC バランスバー（エネルギー比: P×4 / F×9 / C×4）
   - 目標達成率（目標未設定時は達成率を出さない）
   - 「目標を設定」ボタン → モーダル（PUT /meals/goals）
3. **食事区分セクション × 4**（朝食 / 昼食 / 夕食 / 間食）
   - 区分小計（kcal + P/F/C）
   - エントリ一覧（名前・数量・栄養値・メモ）
   - 追加 / 編集 / 削除
4. **推移セクション** — `?from=&to=`（既定: 直近 30 日）
   - kcal 折れ線チャート（`BaseChart.vue` + `--chart-1`）
   - PFC 積み上げ棒チャート（`--chart-2`〜`--chart-4`）
   - **2 軸 1 枚にしない**（チャートは 2 枚に分ける）

## エントリ追加モーダル

2 タブ構成:

| タブ | 内容 |
|---|---|
| マイ食品から | インクリメンタル検索（上限 20 件・更新日降順）。選択後に数量（サービング倍率）を入力 |
| 直接入力 | 名前 + 数量 + kcal / P / F / C を手入力。「マイ食品にも登録する」フラグ可 |

- 数量は **サービング倍率のみ**（グラム基準は v0 対象外）
- マスタ選択時の栄養値はサーバー側で「1 サービング値 × quantity」を計算してスナップショット確定
- 直接入力時は受け取った栄養値をそのままスナップショット確定

## 確定済みの設計判断

| 論点 | 決定 |
|---|---|
| 数量入力 | サービング倍率のみ。グラム基準は v0 対象外 |
| 目標設定 UI | /meals サマリカードのモーダル。設定画面には置かない |
| 食品初期データ | シーダーなし（空スタート）。開発用は Factory |
| 栄養素スコープ | kcal + P / F / C の 4 値のみ |
| 削除方針 | meal_entries 物理削除 / food_items soft delete |
| 外部食品 DB・バーコード・写真・点数化 | v0 対象外。実装しない |

## ルート

`/meals/foods` と `/meals/goals` を `/meals/{mealEntry}` より先に登録する。

| Method | Path | Controller | 備考 |
|---|---|---|---|
| GET | /meals | MealEntryController@index | Inertia。`?date=` `?from=` `?to=` |
| POST | /meals | MealEntryController@store | |
| PATCH | /meals/{mealEntry} | MealEntryController@update | |
| DELETE | /meals/{mealEntry} | MealEntryController@destroy | 物理削除 |
| GET | /meals/foods | FoodItemController@index | Inertia。`?query=` 時は JSON |
| POST | /meals/foods | FoodItemController@store | |
| PATCH | /meals/foods/{foodItem} | FoodItemController@update | |
| DELETE | /meals/foods/{foodItem} | FoodItemController@destroy | soft delete |
| PUT | /meals/goals | NutritionGoalController@upsert | |

## 集計・レイヤリング

| クラス | 責務 |
|---|---|
| GetDailyMealsQuery | 指定日のエントリを区分別グルーピング + 区分小計 + 日次合計 + 目標値 |
| GetNutritionChartQuery | 期間内の日別 SUM(kcal, P, F, C)。期間指定必須、全件取得禁止 |
| SearchFoodItemsQuery | user スコープ + name 部分一致 + limit 20 + 更新日降順 |
| CreateMealEntryService | スナップショット確定。`register_as_food` で food_items 同時登録 |
| UpdateMealEntryService | 同上（再計算してスナップショット更新） |
| DeleteMealEntryService | 物理削除 |
| CreateFoodItemService / UpdateFoodItemService / DeleteFoodItemService | マイ食品 CRUD（削除は soft delete） |
| UpsertNutritionGoalsService | user_id unique で upsert |

スナップショット方針は [ADR-0009](../../adr/0009-meal-records-snapshot.md) を正とする。

## バリデーション

### POST/PATCH /meals（MealEntry）

| フィールド | ルール |
|---|---|
| eaten_on | required, date |
| meal_type | required, in: breakfast,lunch,dinner,snack |
| food_item_id | nullable, ulid, 自分の food_items に存在 |
| name | required, string, max:100 |
| quantity | required, numeric, min:0.1, max:100 |
| kcal | food_item_id なし時 required。numeric, min:0, max:9999 |
| protein_g | food_item_id なし時 required。numeric, min:0, max:999 |
| fat_g | food_item_id なし時 required。numeric, min:0, max:999 |
| carb_g | food_item_id なし時 required。numeric, min:0, max:999 |
| note | nullable, string, max:500 |
| register_as_food | sometimes, boolean |

- `food_item_id` あり: クライアントの kcal/P/F/C は無視し、マスタ × quantity でサーバー計算
- `food_item_id` なし: 受け取った栄養値をそのまま確定（直接入力）
- `register_as_food=true` かつ直接入力時: food_items に 1 サービング分として同時登録

### POST/PATCH /meals/foods（FoodItem）

| フィールド | ルール |
|---|---|
| name | required, string, max:100 |
| serving_label | required, string, max:50 |
| kcal | required, numeric, min:0, max:9999 |
| protein_g | required, numeric, min:0, max:999 |
| fat_g | required, numeric, min:0, max:999 |
| carb_g | required, numeric, min:0, max:999 |

### PUT /meals/goals（NutritionGoal）

| フィールド | ルール |
|---|---|
| kcal | required, numeric, min:0, max:20000 |
| protein_g | required, numeric, min:0, max:999 |
| fat_g | required, numeric, min:0, max:999 |
| carb_g | required, numeric, min:0, max:999 |

## 認可

- 自分の食事・食品・目標のみ操作可能（Policy + `user_id` スコープ）
- 他ユーザーのデータが返らない・操作できないことを Feature テストで固定する

## 想定件数・パフォーマンス

- meal_entries: 1 日あたり数〜十数件 × 365 日/年。一覧は日付指定、推移は期間指定 + 日別 SUM
- food_items: ユーザーあたり数十〜数百件想定。検索は limit 20
- 全件取得禁止。グラフは期間必須

## `/records` からの導線

コンディション管理（`/records`）に「食事」カードを追加し、当日の合計 kcal を表示して `/meals` へリンクする。
