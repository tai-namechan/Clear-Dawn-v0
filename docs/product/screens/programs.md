# プログラム管理・ロードマップ画面 仕様

対象: セルフマネジメントOS拡張 Phase 1〜2（2026-07-16 確定）
関連: [goals.md](./goals.md)、[routines.md](./routines.md)、[ADR-0012](../../adr/0012-program-layer-on-routine-engine.md)

## 目的

トレーニングプログラム（例: 11週 筋力+投球+栄養統合プログラム）を **実行可能なDBデータ** として管理し、
既存ルーティンエンジンの「日次プラン」を生成する上位層を提供する。PDFは添付資料（参照）に格下げする。

## 登録済みプログラム

| プログラム | 期間 | 登録コマンド |
|---|---|---|
| 11週 統合プログラム（筋力・投球・栄養） | 2026-07-16 〜 2026-10-01 | `php artisan cleardawn:install-program` |
| ヴァイオリン復帰プログラム（10週） | 2026-07-27 〜 2026-10-01 | `php artisan cleardawn:install-violin-program` |

どちらも**冪等**（同名プログラムが既にあれば何もしない）。プログラム由来プランの一意制約は
`(user_id, program_version_id, scheduled_on)` なので、**同じ日に複数プログラムのプランが並立する**
（例: 土曜 = 投球DAY5 + ヴァイオリンDAY E）。

## 構造

```
Program（プログラム）
  └─ Version（版。編集はコピーオンライトで新版を作る）
      ├─ Phase（基礎/減量/強化/調整/測定 = intent）
      │   └─ Week（W1..Wn、開始日、週の意図）
      ├─ DAY テンプレート（DAY1..n。曜日固定 or 順番制。優先度 never_cut/keep/cut_ok）
      │   └─ STEP（step_kind: preparation/movement/power/throwing/strength/accessory/arm_care/
      │            conditioning/cooldown/technique/reading/repertoire/review。順序付き）
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

## 実装メモ（2026-07-17）

実装済み: 一覧・詳細・ロードマップ（表示）、`POST /programs/{program}/versions`（版改訂 C・コピーオンライト）、seed/install。

**後回し**（詳細 ID は [progress.md 後回しバックログ](../../progress.md)）:

- day/step/item CRUD・週処方 upsert → SM-D01
- `program_attachments` アップロード UI → SM-D02
- 承認 B（期間調整・未実行プラン再生成）→ SM-D03
- ロードマップの実績状態（セッション連携）→ SM-D07

## 重量の扱い（個人値分離）

- メインリフト処方は **基準リフト1RM比（percent_of_reference）** で保存する。
- 個人の現在1RMは personal_profile_entries（有効日付き）に保存し、`php artisan cleardawn:import-personal` で投入する（seeder・リポジトリには個人値を含めない）。
- 表示重量 = 1RM × percent を 1.25kg 単位に丸め（r125）。1RM更新で全週が再計算される（実行済みスナップショットは不変）。
- 補助種目は「指定レップを RPE7–8 で終えられる重さ」ガイダンス + 任意の個人上書き。

## 週別処方 → ルーティンステップの反映

週別処方（`program_week_item_prescriptions`）は「その週だけ変わる指示」を持つ層で、
DAYテンプレートからプランを生成するときに各ステップへスナップショットされる。

| 処方の列 | 生成されたプランステップ |
|---|---|
| `fixed_load` / `percent_of_reference` | `target_load`（比率は1RM×比率を1.25kg丸め） |
| `sets` | `target_blocks` |
| `reps` | `target_amount` |
| `intent` / `note` | `note` に合成（種目のキュー・RPE等と `/` 区切りで連結） |

`intent` / `note` を載せることで、**週ごとに文言が変わる処方**（音階の調、カノンの到達小節、
今週のPRACTICE CUE、進級条件など）がその日のルーティンステップにそのまま現れる。
数値の処方（メインリフトの重量）と非数値の処方（音楽の指示）を同じ仕組みで扱う。

## ヴァイオリン復帰プログラム（10週）

添付資料（HTML/PDF）を実行可能データへ落としたもの。2026-07-27 〜 10-01 の10週。

- **フェーズ**: 再接触(W1-2, base) / 技術構築(W3-6, intensify) / 再現性・模擬(W7-9, test) / 仕上げ(W10, taper)
- **DAY**: 曜日固定7本（A 月=音・基礎＋カノン / B 火=ミニ技術 / C 水=譜読み＋篠崎 /
  B2 木=ミニ修正 / D 金=音楽性＋録音 / E 土=観察・非実奏 / F 日=週次テスト）＋
  `VIOLIN-X`（10分フォールバック、`assignment_mode = fallback`）
- **STEP種別**: 音楽向けに `technique`（開放弦・音階・4音セル）、`reading`（音名・リズム・先読み・動画観察）、
  `repertoire`（カノン・篠崎・好きな曲）、`review`（録音・記録・翌週計画）を使う
- **週別処方**: 音階（`fixed_load` = ♩bpm、`load_unit` = `bpm`）、カノンの到達小節、音・身体の focus、
  譜読み課題、教本・曲、週末テスト、進級条件、今週のCUE
- **制約**: 安全ゲート V1〜V8、テンポ進行ルール、1軸のみ進級、10分フォールバック、
  「証明しない設計」、Exit Criteria、7原則、弓の技術、1音ループ、評価5点、曲ロードマップ、本番前ルーティン

`VIOLIN-X`（10分フォールバック）は、疲労・残業・睡眠不足の日にその日のDAYと差し替えて使う（V7）。
**疲労は自動判定できないため自動生成では選ばれない。**

## DAY の割当方式（assignment_mode）

| 方式 | 自動生成 | 消費 | 用途 |
|---|---|---|---|
| `weekday_fixed` | ISO曜日が一致する日に生成 | しない | 投球=土曜固定、ヴァイオリンDAY A=月曜 等 |
| `sequential` | 曜日固定DAYが無い日に、未割当の先頭から生成 | **版の中で1回だけ** | 順番制のDAY |
| `fallback` | **生成しない**（手動で差し替える） | しない | 短縮版DAY（`VIOLIN-X`） |

`sequential` は版の中で1回消費されると二度と選ばれない。短縮版DAYを `sequential` にすると
最初の1日だけ生成されて以降そのプログラムのプランが作られなくなるため、`fallback` を使う。

先生の言葉（原文保存層）・参考URL・個人が特定できる情報はプログラムに**入れない**（資料側に残す）。

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
