# 目標設定画面 仕様

対象: セルフマネジメントOS拡張 Phase 1（2026-07-16 確定）
関連: [programs.md](./programs.md)、[ADR-0010](../../adr/0010-program-layer-on-routine-engine.md)

## 目的

「将来どうなっていたいか」（TOP Matrix future 行）を、達成指標つきの実行可能な目標として構造化する。
**ダッシュボード（TOP Matrix）は変更しない。** goals 側から `matrix_cells` への任意の片方向参照のみ持つ。

## 画面とルート

| 画面 | ルート | 役割 |
|---|---|---|
| 目標一覧 | GET /goals | 階層ツリー・状態・期日・指標サマリ |
| 目標詳細 | GET /goals/{goal} | 指標ごとの現在地/目標値・関連プログラム・変更履歴 |

API: POST /goals、PATCH/DELETE /goals/{goal}、POST /goals/{goal}/metrics、PATCH/DELETE goal-metrics。

## 必須項目

- 目標名 / 期日 / なぜ達成したいか / 優先順位 / 状態（draft・active・achieved・abandoned）
- 親目標（自己参照。例: 競技復帰 → 球速・BIG3・身体組成・痛みなく継続・週次継続）
- 元となるダッシュボード項目（matrix_cell への参照・任意）
- 関連プログラム
- 1つ以上の達成指標（metric 参照 + 現在値/目標値/単位/測定方法）
- 変更履歴（goal_change_logs: 変更内容と理由を必ず記録）

## 設計原則

1. **合成スコアを作らない。** 指標ごとの現在地と目標を並べて表示する（単一の達成率に潰さない）。
2. **指標は metrics マスタを拡張して使う**（nullable user_id でユーザー定義指標を追加。`is_advanced` で専門測定値は既定非表示）。
3. 変更（目標値・期日・指標）は理由つき履歴を残す。
