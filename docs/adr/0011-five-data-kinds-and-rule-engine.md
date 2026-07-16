# ADR-0011: 5データ種の分離と決定論ルールエンジン

- **状態**: 承認済み
- **日付**: 2026-07-16
- **関連**: [ADR-0008](./0008-condition-records-hybrid-schema.md)、[today-ops.md](../product/screens/today-ops.md)、BODY MONITOR 設計書 v1.2（リポジトリ外）

## 文脈

コンディション・食事・安全ゲート・AI提案を統合するにあたり、「生データ」「計算結果」「助言」「ユーザー判断」「実績」を単一の condition_score やメモ欄に混ぜると、再現性・説明可能性・過去実績の保全が壊れる。

## 決定

1. **5データ種を別テーブルで分離する。**
   - 生データ: daily_checkins / symptom_observations / metric_records（source・実測/推定・信頼度列を追加）/ routine_block_logs
   - 計算結果: daily_resource_states（EWMA・z_load・relStrain・readiness）/ personal_baselines（再計算可能キャッシュ）
   - 助言: rule_evaluations / recommendations（+options）
   - ユーザー判断: recommendation_decisions（選択・理由）
   - 実績と結果: routine_sessions 系 / outcome_evaluations
2. **ルールエンジンは決定論の純粋計算 Service 群**（同入力同出力。前例: Yoyu GapAnalyzer/MarginAnalyzer）。閾値・係数は `rule_definitions.params`（種別 evidence_rule / clinician_rule / user_policy / program_rule / ai_suggestion、根拠・対象母集団・限界・確信度・検証者・版付き）としてデータで持ち、コード定数にしない。
3. **AI は説明・優先順位付け・代替案・下書きのみ。** 生データ書換・推定の実測保存・無承認のプラン確定・自動補完は構造上できない（AiGateway 経由 + recommendations の必須フィールド）。
4. **採用しないもの**: 固定閾値ACWR赤線ゾーン / GIRD≥20°判定 / 単一 readiness 合成スコア / 閾値の自動引き上げ（実験的機能としても当面実装しない）。
5. **提示の階層**: 割り込みはハードゲート（H1〜H7）のみ・1日1件・クールダウン48h。ソフトアラートはカード+強行ログ（reason_code / 判断根拠スナップショット）。週次は俯瞰レポート。

## 影響

- モジュール有効/無効は user_module_settings（module_key × enabled）+ 画面表示条件のみ。プラグイン基盤は作らない
- 較正期間（ベースライン4週未満）は警告を発しない
- H7（尺骨神経症状）は初期状態ロック。解除は受診結果の入力による（S12 肩ROM は未測定のため休止から開始）
