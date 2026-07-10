# ADR-0007: Training System から Routine System への転換（汎用化と命名）

- **状態**: 承認済み
- **日付**: 2026-07-08
- **関連**: [ADR-0006](./0006-training-system-plan-run-snapshot.md)、[routine-system-redesign.md](../product/routine-system-redesign.md)

## 文脈

M3 で実装した Training System は筋トレ専用の語彙・ハードコード列（`target_weight_kg` 等）で構築されており、バイオリン練習・資格勉強・生活習慣への転用ができない。一方、ADR-0006 の plan/run 分離・2段スナップショット設計は「ルーティン実行システム」としてそのまま有効である。

## 決定

1. **作り直しではなく転換とする。** ADR-0006 の構造（実施項目 → ルーティン → 実行プラン → セッション → ブロックログ）は維持し、命名と記録値の汎用化・UI再構成を行う。
2. **移行方式は案A（リセット）。** マイグレーション `2026_07_08_000001`〜`000009` を新テーブル名・新列構成に直接書き換え、`migrate:fresh` で再構築する。リネーム用の追加マイグレーションは積まない。
3. **記録値の汎用化。** ステップ3表の `target_weight_kg` / `target_reps` 等を廃止し、`target_load`+`load_unit` / `target_amount`+`amount_unit` / `target_blocks` に統一する。ブロックログも同様。
4. **用語マップ（正）** をコード・UI・ドキュメント全体で採用する（詳細は再設計書 §3 参照）。
5. **ステータス簡素化。** ルーティンから生成されたプラン（ステップ≥1）は最初から `ready`。空プランのみ `draft`、ステップ追加で自動 `ready`。

## 理由

- DB を `training_*` のまま作り込む前が転換の最適タイミング
- 保持すべき本番データがほぼなく（409バグ等）、リセットの複雑さに見合わない
- 汎用列設計により M4 記録モジュールとの接続（項目×単位の時系列化）が可能になる

## 影響

- ADR-0006 の決定事項は名前を読み替えて存続（plan→RoutinePlan、run→RoutineSession 等）
- `activity_logs` の morph alias は `training_run` → `routine_session`
- イベント種別は `training_run_completed` → `routine_session_completed`
- フロントは4タブ構成（今日の実行 / ルーティン / 実施項目 / 記録）
- ローカル開発は `migrate:fresh` でよい。本番は users / matrix / videos / metrics を保持するため **ルーティン系だけ DROP → CREATE**（`2026_07_09_000000_rebuild_routine_system_preserving_matrix`）でスキーマを揃える。旧 Training / 旧ルーティンのデータ移行は行わない

## 補足（本番スキーマずれ）

ADR-0007 の案Aは create migration を直接書き換えたため、既に旧 Training スキーマで migrations が「済」の本番では物理スキーマとコードが乖離する。本番パスは full `migrate:fresh` ではなく、上記 rebuild migration でルーティン系のみ再構築する。
