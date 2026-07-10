# ADR-0008: 記録系ハイブリッドスキーマ（メトリクス汎用 + 食事専用）

- 状態: 承認済み（M4）

## 文脈

Phase 2.5（M4）で体重・睡眠などのコンディション記録と、食事記録を扱う。
すべてを汎用 `metric_records` に押し込むと、食事の区分・数量・PFC スナップショット・
マイ食品マスタといった構造を表現しきれない。一方、すべてを専用テーブルにすると
単純数値系（体重・睡眠）の実装コストが上がる。

## 決定

**ハイブリッド**を採用する。

| 対象 | 方式 | テーブル |
|---|---|---|
| 単純数値系（体重・睡眠・気分等） | 汎用 | `metrics` + `metric_records` |
| 食事記録 | 専用 | `food_items` + `meal_entries` + `nutrition_goals` |

- 筋力・野球の専用テーブルは M4 の別マイルストーンで確定する（本 ADR の範囲外）
- 食事のスナップショット方針は [ADR-0009](./0009-meal-records-snapshot.md)

## 理由

1. 単純数値系は upsert（unique: user_id + metric_id + recorded_on）で十分
2. 食事は 1 日複数エントリ・区分・マスタ参照・栄養スナップショットが必要で汎用テーブルに無理がある
3. 既存の MetricRecord 縦断実装を壊さず、食事を独立して縦断実装できる

## 影響

- `/records` はコンディション（メトリクス）専用。食事は `/meals`
- `/records` から食事への導線カードを置く（サイドバーには追加しない）
- チャートライブラリは ECharts（`BaseChart.vue`）を継続利用する

## 関連

- [records.md](../product/screens/records.md)
- [meals.md](../product/screens/meals.md)
- [tables.md](../data/tables.md)
- [ADR-0009](./0009-meal-records-snapshot.md)
