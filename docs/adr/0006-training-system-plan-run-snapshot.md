# ADR-0006: Training System — plan/run 分離とスナップショット

## Status

Accepted (M3)

## Context

トレーニング機能では、テンプレート（routine）・当日メニュー（plan）・実行実績（run）の3層が必要。
テンプレート編集や種目名変更が過去の実績表示を壊さないよう、スナップショット方針が必要。

## Decision

1. **plan / run 分離**: 1 plan : 多 run。1日に複数 plan 可（unique なし）
2. **2段スナップショット**:
   - routine → plan（`CreateTrainingPlanService`、作成時）
   - plan → run（`StartTrainingRunService`、開始時。`exercise_name` を文字列確定）
3. **activity_logs**: `training_run_completed` イベント（subject = TrainingRun）
4. **run は削除不可**（不変の事実ログ）
5. **plan 削除**: run が0件のときのみ物理削除。run 発生後は archive のみ
6. **history / metric_records**: ページネーション・期間指定必須（全件取得禁止）

## Consequences

- テンプレ・種目変更は将来の plan/run にのみ影響
- run_steps.exercise_name により種目 soft delete 後も表示可能
- Feature テストでスナップショット独立性を固定
