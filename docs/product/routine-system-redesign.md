# Routine System 再設計書（Training System からの転換）

- 作成日: 2026-07-08
- ステータス: **実装済み**（ADR-0007 承認）
- 関連: [ADR-0006](../adr/0006-training-system-plan-run-snapshot.md)、[ADR-0007](../adr/0007-routine-system-conversion.md)、[screens/routines.md](./screens/routines.md)

## 結論

作り直しではない。ADR-0006 の plan/run 分離・2段スナップショットは Routine System として継続。変更は次の3系統:

| 系統 | 内容 |
|---|---|
| A. リネーム | テーブル・モデル・Enum・ルート・UI用語 |
| B. 記録値の汎用化 | 負荷+単位 / 量+単位 / ブロック数 |
| C. UI再構成 | 4タブ・ルーティン中心・インライン作成 |

## 用語マップ

| 旧（コード） | 新（コード） | 新（UI） |
|---|---|---|
| Exercise | RoutineItem | 実施項目 |
| TrainingPlan | RoutinePlan | 今日の実行プラン |
| TrainingRun | RoutineSession | 実行セッション |
| TrainingSetLog | RoutineBlockLog | ブロック |
| target_sets | target_blocks | ブロック数 |
| target_weight_kg | target_load + load_unit | 負荷 |

## テーブル対応

```
routine_items            ← exercises
routines                 （維持）
routine_steps            （列汎用化）
routine_plans            ← training_plans
routine_plan_steps       ← training_plan_steps
routine_sessions         ← training_runs
routine_session_steps    ← training_run_steps
routine_block_logs       ← training_set_logs
```

## ルート対応

| 旧 | 新 |
|---|---|
| GET /training | GET /today |
| /exercises | /routine-items |
| /training/plans/* | /plans/* |
| /training/runs/* | /sessions/* |
| /training/sets/* | /blocks/* |

## 移行方式

**案A（リセット）** — マイグレーション直接書き換え + `migrate:fresh`

## 実装フェーズ状況

- [x] Phase 0: 残バグ修正（reorder JSON、Resource resolve、日付）
- [x] Phase 1: ADR-0007、screens/routines.md
- [x] Phase 2: DB + バックエンド転換
- [x] Phase 3: フロント基盤（types、共通コンポーネント、タブ）
- [x] Phase 4: 画面実装（S1-S8 基本版 — ルート・タブ・CRUD・実行フロー）
- [x] Phase 4b: モックアップ準拠UI（S3 ルーティン編集 / S4 ステップ追加モーダル / S7 実行画面）
- [ ] Phase 5: 記録接続（/history 拡張、グラフ系列化）

詳細仕様の全文は本リポジトリ導入時の設計ドラフトを参照。実装の正はコードと [screens/routines.md](./screens/routines.md)。
