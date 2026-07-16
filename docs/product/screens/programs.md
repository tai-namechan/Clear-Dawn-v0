# プログラム管理・ロードマップ画面 仕様

対象: セルフマネジメントOS拡張 Phase 1〜2（2026-07-16 確定）
関連: [goals.md](./goals.md)、[routines.md](./routines.md)、[ADR-0010](../../adr/0010-program-layer-on-routine-engine.md)

## 目的

トレーニングプログラム（例: 11週 筋力+投球+栄養統合プログラム）を **実行可能なDBデータ** として管理し、
既存ルーティンエンジンの「日次プラン」を生成する上位層を提供する。PDFは添付資料（参照）に格下げする。

## 構造

```
Program（プログラム）
  └─ Version（版。編集はコピーオンライトで新版を作る）
      ├─ Phase（基礎/減量/強化/調整/測定 = intent）
      │   └─ Week（W1..Wn、開始日、週の意図）
      ├─ DAY テンプレート（DAY1..n。曜日固定 or 順番制。優先度 never_cut/keep/cut_ok）
      │   └─ STEP（step_kind: preparation/movement/power/throwing/strength/accessory/arm_care/conditioning/cooldown。順序付き）
      │       └─ 種目処方（routine_item 参照 + sets/reps/amount/固定重量/基準リフト比/RPE/休憩/左右/テンポ/キュー/必須度/代替）
      ├─ 週別処方（week × step_item: メインリフトの W1-W11 重量表の実体）
      ├─ 選択グループ（例: 水曜 = 上半身補助/ヨガ/ロードワーク/完全休養 から選ぶ）
      ├─ 制約（program_rule: 配置原則・投球上限・削減優先順位・最低実行ライン）
      ├─ 指標目標 / 栄養計画参照 / 添付（PDF等）
```

## 画面とルート

| 画面 | ルート | 役割 |
|---|---|---|
| プログラム一覧 | GET /programs | 名 / 版 / 期間 / 現在週 / 状態 / 今週の進捗 |
| プログラム詳細 | GET /programs/{program} | 目的・フェーズ・DAY/STEP/処方・制約・変更履歴・添付 |
| ロードマップ | GET /programs/{program}/roadmap | フェーズ帯 + 週タブ（W1..Wn）+ DAYカード + 実績状態。実行への入口 |

API: programs CRUD、versions（POST /programs/{program}/versions = 版改訂）、day/step/item CRUD、week 処方 upsert。

## 重量の扱い（個人値分離）

- メインリフト処方は **基準リフト1RM比（percent_of_reference）** で保存する。
- 個人の現在1RMは personal_profile_entries（有効日付き）に保存し、`php artisan cleardawn:import-personal` で投入する（seeder・リポジトリには個人値を含めない）。
- 表示重量 = 1RM × percent を 1.25kg 単位に丸め（r125）。1RM更新で全週が再計算される（実行済みスナップショットは不変）。
- 補助種目は「指定レップを RPE7–8 で終えられる重さ」ガイダンス + 任意の個人上書き。

## 承認3段（変更フロー）

| 段 | 対象 | 動作 |
|---|---|---|
| A 今日だけ | 今日の RoutinePlan スナップショット | プラン編集のみ。プログラム不変。理由と結果を記録 |
| B 期間調整 | 指定期間の未実行プラン | 影響セッション一覧を提示 → 承認後に再生成。版は不変 |
| C 版改訂 | プログラム本体 | 差分+影響を提示 → 明示承認 → 新 program_version。旧版と実行済み記録は保持 |

## 設計原則

1. 実行済み記録を書き換える変更は存在しない（スナップショット原則 ADR-0006 の上位適用）。
2. DAY番号と曜日は分離（weekday_fixed / sequential / 混合）。
3. 種目は筋トレに限定しない（投球・ドリル・ヨガ・ラン・ストレッチ・ケアを同じ STEP 構造で処方）。
4. 過度な汎用化をしない（module_key + 表示条件で足りるものにプラグイン基盤を作らない）。
