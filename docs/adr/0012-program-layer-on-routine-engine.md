# ADR-0012: ルーティンエンジン上のプログラム層（版管理・DAYテンプレート・プラン生成）

- **状態**: 承認済み
- **日付**: 2026-07-16
- **関連**: [ADR-0006](./0006-training-system-plan-run-snapshot.md)、[ADR-0007](./0007-routine-system-conversion.md)、[programs.md](../product/screens/programs.md)

## 文脈

11週トレーニングプログラム（筋力+投球+栄養）を実行可能データとして管理する必要がある。既存のルーティンエンジン（テンプレート→日付プラン→実行セッションの3段スナップショット）は日次実行に十分だが、「期間・フェーズ・週・DAY・週別処方・版」という上位構造を持たない。

## 決定

1. **並行する新実行エンジンは作らない。** Program 層（programs / program_versions / program_phases / program_weeks / program_day_templates / program_day_steps / program_step_items / program_week_item_prescriptions / program_choice_groups / program_choice_options / program_constraints / program_metric_targets / program_attachments）を新設し、DAYテンプレートから既存 `RoutinePlan`+`RoutinePlanStep` を生成する。実行・実績は既存 `RoutineSession` 系を拡張して使う。
2. **編集はコピーオンライトの版管理。** プログラム本体の改訂は新 `program_version` を作る（旧版・実行済み記録は不変）。今日だけの調整はプランスナップショットの編集で完結する（承認3段 A/B/C）。
3. **DAY番号と曜日を分離する。** `assignment_mode` = weekday_fixed（投球=土曜固定等）/ sequential（未実行DAYの先頭から順に割当）。
4. **メインリフト処方は基準リフト1RM比（percent_of_reference）で保存する。** 個人の1RM実測値はリポジトリに含めず、`personal_profile_entries`（有効日付き）へ import コマンドで投入する。表示重量は 1RM×比率を1.25kg丸めで導出。
5. **routine_plans / plan_steps に program 参照列（全て nullable）を追加**し、既存の手動ルーティン運用は無変更で共存させる。

## 理由

- ADR-0006 のスナップショット原則が「過去の実行内容はプログラム編集で変わらない」という本要求と完全に一致する
- リポジトリは public のため、個人実測値（1RM・体組成）は構造と分離する必要がある

## 影響

- Phase 1 で migration 約18本。既存テーブルへの変更は nullable 列追加のみ（後方互換）
- ルート追加: /goals, /programs, /programs/{program}/roadmap（グローバルバインディング {item}{p}{s}{ss}{bl}{metric} は使用しない）
