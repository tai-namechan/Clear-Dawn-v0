# 実装進捗（v0）

> 最終更新: 2026-07-18  
> 正: [roadmap.md](./roadmap.md) のマイルストーン定義。本ファイルは **実装の現在地** を可視化する。

## セルフマネジメントOS拡張（2026-07-16〜 / ADR-0010・0011）

| Phase | 内容 | 状態 |
|---|---|---|
| 1 | 目標・プログラム・ロードマップ（goals/programs 系テーブル・画面・seed・個人値 import） | **done** |
| 2 | 今日の実行（プログラム→プラン生成・DAY/STEP 実行 UI・型付き実績） | **done**（縦断） |
| 3 | コンディション・食事再構成（チェックイン・症状・栄養プロファイル・タブ化） | **partial**（タブ UI 反映済。症状専用画面・H7リストは後回し） |
| 4 | 決定論の作戦カード（rule_definitions・recommendations・承認 tier A） | **done**（縦断・承認A）。B/C UI は薄い |
| 5〜7 | レポート/Kioku 学び・AI コーチ・Yoyu 連携 | not_started |

Phase 1 の縦断（2026-07-17 完了）:

| 項目 | 根拠 |
|---|---|
| Migrations（goal/program/personal_profile/module + metrics 拡張） | `2026_07_16_1000*` 4本（metrics key は (user_id, key) ユニークに変更） |
| Models / Enums / Factories | `app/Models`（18新規 + User/Metric 拡張）、`app/Enums` 12種 |
| seed（11週プログラム） | `InstallElevenWeekProgramService` + `php artisan cleardawn:install-program {userId}`（冪等） |
| 個人値 import | `php artisan cleardawn:import-personal {userId} --path=personal/profile.json`（gitignore 済み personal/ から投入） |
| 画面 | `/goals`・`/goals/{goal}`・`/programs`・`/programs/{program}`・`/programs/{program}/roadmap`（1RM×比率の表示重量は r125 丸め） |
| Feature テスト | `GoalTest`・`ProgramTest`・`ProgramInstallTest`（計26件） |

Phase 2〜4 の縦断（2026-07-17 追加）:

| 項目 | 根拠 |
|---|---|
| Phase 2 migrations | `2026_07_17_100001` routine_* に program 参照・型付き実績列 |
| プラン生成 | `GenerateProgramDayPlansService`（weekday_fixed / sequential / 選択日）。`/today` アクセスで冪等生成 |
| 承認 A | `ApplyTodayPlanAdjustmentService` + `POST plans/{p}/today-adjust` / 作戦カード決定 |
| 版改訂 C | `ReviseProgramVersionService` + `POST programs/{program}/versions`（コピーオンライト） |
| Phase 3 | `daily_checkins` / `symptom_observations` / `daily_resource_states` / `personal_baselines` / `nutrition_target_profiles` / `measurement_sources` + metric_records 拡張 |
| Phase 4 | `rule_definitions`〜`outcome_evaluations`、`EvaluateRulesForDayService`、作戦カード UI（`Today/Index`） |
| Feature テスト | `ProgramDayPlanGenerationTest`・`TodayOpsPhaseTest`・`ProgramVersionReviseTest` |

### 後回しバックログ（意図的スコープ外・忘れ防止）

> 2026-07-17 判断: Phase 2〜4 の本線（生成・チェックイン・作戦カード承認A・版改訂C）は縦通した。  
> 下記は **バグではなく後続フェーズで拾う項目**。仕様の正は各画面 docs / ADR。着手時は本表を更新する。

| ID | 項目 | 現状 | 拾う目安 | 仕様の正 | 実装の手がかり |
|---|---|---|---|---|---|
| SM-D01 | DAY/STEP/item CRUD・週処方 upsert API | 閲覧のみ（seed/install で投入） | Phase 2 仕上げ or プログラム編集マイルストーン | [programs.md](./product/screens/programs.md) | `ProgramController` は read のみ。FormRequest/Service 未作成 |
| SM-D02 | `program_attachments` アップロード UI | テーブル・Model のみ | 同上（編集と同時が自然） | programs.md / tables.md Phase1 | `ProgramAttachment` 既存。Video 署名付き URL パターンを流用可 |
| SM-D03 | 承認 B（期間調整・未実行プラン再生成） | 未実装 | Phase 4 拡張 or Phase 5 手前 | programs.md 承認3段 / today-ops.md | A=`ApplyTodayPlanAdjustmentService`、C=`ReviseProgramVersionService` の間に Service を新設 |
| SM-D04 | ハードゲート割り込み 1日1件・48h クールダウン永続化 | 当日評価内の `interruptUsed` のみ | Phase 4 仕上げ | ADR-0011 / today-ops.md | `rule_evaluations` or 専用 cooldown 行で last_interrupt_at を保持 |
| SM-D05 | `outcome_evaluations` 事後評価 UI | テーブルのみ | Phase 5（週次レポート）と同時が自然 | ADR-0011 | Model/Factory 済み。セッション完了後の入力導線が未配線 |
| SM-D06 | コンディション専用の症状・H7 仕上げ | `/records/condition` は 今日/推移/設定タブ化済。症状・受診リストは未 | Phase 3 仕上げ | today-ops.md / records.md | H7 受診依頼リスト・古い測定データの専用画面 |
| SM-D07 | ロードマップの実績状態表示 | 処方重量表示まで。セッション実績連携なし | Phase 2 プラン連携の延長 | programs.md ロードマップ | `GetProgramRoadmapQuery` + `routine_plans`/`sessions` を週×DAY で集約 |
| SM-D08 | today-ops 表示順の残り（未入力測定・受診依頼リスト） | 作戦優先 UI + コンディション/食事リンクまで | Phase 3〜4 仕上げ | today-ops.md 表示順 6–7 | `GetTodayOpsQuery` に stale metrics / H7 visit list を追加 |

各マイルストーンは「Route → Controller → Query/Service → Vue → テスト」の縦断で完結させる。

## サマリ

| MS | Phase | 内容 | 状態 | 残作業の要点 |
|---|---|---|---|---|
| M0 | — | docs + デザイン基盤 | partial | 装飾 PNG 欠落、フォント配信方針（CDN 利用中） |
| M1 | 1 | TOP Matrix / 領域 / activity_logs 記録開始 | **done** | — |
| M2 | 1.5 | 日次・週次振り返り（メモはキオク移管） | **not_started** | 振り返り未着手。汎用メモは作らない |
| M3 | 2 | ルーティン / トレーニング + /history | **done** | イベント名の docs ドリフトあり（下記） |
| M4 | 2.5 | 記録 + グラフ + 食事（M4b） | **partial** | 週次平均・筋力チャート UI・構造化野球など。食事は縦断済 |
| M5 | 3 | Finance | **not_started** | スコープ未決定 #7 |
| M6 | 3.5 | 動画 | **partial** | 尺制限・非同期削除・サムネ等の仕上げ |
| M7 | 4 | AI 支援 | **not_started** | プロバイダ未決定 #6 |
| M8 | 4.5 | Export API | **not_started** | 認証方式未決定 #8 |
| SK1 | Seed K | ProductSwitcher + プレースホルダ | **done** | 切替シェル完了 |
| SK2 | Seed K | キオク P0 | **done** | 保存・一覧・検索 |
| SK3 | Seed K | キオク P1 | **done** | AI整理・レジストリ・usage log |
| SK4 | Seed K | ヨユウ Today | **done** | モック予定・タスク・頭の中 |
| SK5 | Seed K | ヨユウ秘書 × Recall | **done** | 二層コンテキスト（キー無しはフォールバック） |

状態の意味:

| 状態 | 意味 |
|---|---|
| done | 縦断（migration → 画面 → Feature テスト）が揃い、受入の主目的を満たす |
| partial | 主要パスはあるが、仕様書の一部が未配線・未実装 |
| not_started | コードなし（仕様 docs のみ） |

## 可視化（ロードマップ順）

```text
M0 ████████░░  partial   docs / トークン / レイアウト（素材 PNG 欠）
M1 ██████████  done      TOP Matrix 縦断
M2 ░░░░░░░░░░  not_started  メモ / 振り返り
M3 ██████████  done      ルーティン + /history
M4 ██████░░░░  partial   metrics + 日次入力 + ECharts + 食事 M4b（集計・タブ未完）
M5 ░░░░░░░░░░  not_started  Finance
M6 ███████░░░  partial   署名付き URL アップロード〜再生（仕上げ残）
M7 ░░░░░░░░░░  not_started  AI 支援
M8 ░░░░░░░░░░  not_started  Export API
```

**実装順序の実態**: M2 を飛ばして M3 / M4 / M6 が進んでいる。Export（M8）や AI（M7）は M2 / M5 のデータ有無に依存するモジュールがある。

## レイヤー別チェックリスト

### M0 — docs + デザイン基盤（partial）

| 項目 | 状態 | 根拠 |
|---|---|---|
| docs ツリー | ✅ | `docs/product`, `docs/design`, `docs/data`, `docs/adr` |
| デザイントークン | ✅ | `resources/css/app.css` |
| AppLayout / Sidebar | ✅ | `resources/js/layouts/`, `AppSidebar.vue` |
| フォント | △ | Bunny CDN（`vite.config.ts`）。セルフホスト未確定 #2 |
| 装飾素材 PNG | ❌ | `assets.md` 記載の PNG が `public/images/` に無い |

### M1 — TOP Matrix（done）

| 項目 | 状態 | 根拠 |
|---|---|---|
| Migrations | ✅ | `life_areas` / `matrix_*` / `activity_logs` |
| Models / Policy / FormRequest | ✅ | `app/Models`, `app/Policies`, `app/Http/Requests` |
| Query / Service | ✅ | `GetMatrixBoardQuery`, `ToggleMatrixCellItemCompletionService` 等 |
| Controller + routes | ✅ | `DashboardController`, `LifeAreaController`, `MatrixCellItemController` |
| Vue | ✅ | `Dashboard.vue`, `LifeAreas/`, Matrix モーダル |
| Feature テスト | ✅ | `DashboardTest`, `LifeAreaTest`, `MatrixCellItemTest` |
| activity_logs 記録 | ✅ | `matrix_item_completed` / `matrix_item_reopened` |

### M2 — メモ + 振り返り（not_started）

| 項目 | 状態 | 根拠 |
|---|---|---|
| `memos` / `daily_reviews` / `weekly_reviews` | ❌ | migration なし |
| Controller / routes / Vue | ❌ | `/memos`, `/reviews` なし |
| Feature テスト | ❌ | — |

※ `matrix_cell_items.memo` やルーティン実行メモは M1/M3 の補足欄であり、M2 の思考ログではない。

### M3 — ルーティン + 実行履歴（done）

| 項目 | 状態 | 根拠 |
|---|---|---|
| Migrations（ADR-0007 系） | ✅ | `routine_*` 一式 |
| Today / Plans / Sessions | ✅ | routes + Vue pages |
| `/history` | ✅ | `HistoryController`, `GetActivityHistoryQuery`, `History/Index.vue` |
| activity_logs ルーティン完了 | ✅ | 実装名 `routine_session_completed`（roadmap 表記 `routine_completed` とドリフト） |
| Feature テスト | ✅ | `Routine*Test`, `HistoryTest` |

### M4 — 記録 + グラフ + 食事（partial）

| 項目 | 状態 | 根拠 |
|---|---|---|
| `metrics` / `metric_records` | ✅ | migration + seeder + models |
| 日次 upsert / 一覧 | ✅ | `GET/PUT /records`, `Records/Index.vue` |
| 期間指定グラフ（日次点） | ✅ | `GetMetricChartQuery`, ECharts `BaseChart.vue`, `Records/Show.vue` |
| 週次平均などの集計 | ❌ | 日次 raw のみ。週次 AVG クエリ未実装 |
| 筋力チャート UI | △ | `GetStrengthChartQuery` + テストあり。Controller/Vue 未配線 |
| 仕様の 4 タブ（weight/sleep/strength/baseball） | △ | シードは 6 スカラー指標。構造化野球なし |
| チャートライブラリ | ✅（確定） | ECharts（`BaseChart.vue`）。roadmap #1 クローズ済 |
| 食事記録（M4b） | ✅ | `food_items` / `meal_entries` / `nutrition_goals`、Meals UI、ADR-0009 |
| Feature テスト | ✅ | `MetricRecordTest`, `MetricChartTest`, Meals 系 Feature テスト |

### M5 — Finance（not_started）

| 項目 | 状態 | 根拠 |
|---|---|---|
| テーブル / 画面 / テスト | ❌ | 仕様のみ `docs/product/screens/finance.md` |

### M6 — 動画（partial）

| 項目 | 状態 | 根拠 |
|---|---|---|
| `videos` + 署名付き upload/stream | ✅ | `VideoStorageClient`, upload-url / finalize / stream-url |
| ライブラリ UI | ✅ | `Videos/Index.vue`, `useVideoUpload.ts` |
| ルーティン連携 | ✅ | `routine_steps.video_id` |
| Feature テスト | ✅ | `VideoTest` |
| 仕様ギャップ | △ | 上限 100MB（仕様 50MB）、尺 60s 未強制、削除は同期、サムネなし |

### M7 — AI 支援（not_started）

| 項目 | 状態 | 根拠 |
|---|---|---|
| ルート / サービス / テーブル | ❌ | 仕様のみ `docs/product/screens/ai-assist.md` |

### M8 — Export API（not_started）

| 項目 | 状態 | 根拠 |
|---|---|---|
| API / Sanctum / `/settings/export` | ❌ | 仕様のみ `docs/api/export-api.md`。`routes/api.php` なし |

## ルート実装マトリクス（主要）

| ルート | 仕様 MS | 状態 |
|---|---|---|
| `/dashboard` | M1 | ✅ |
| `/life-areas` | M1 | ✅ |
| `/memos` | M2 | ❌ |
| `/reviews` | M2 | ❌ |
| `/routines`, `/today`, `/sessions/*` | M3 | ✅ |
| `/history` | M3 | ✅ |
| `/records`, `/records/{metric}` | M4 | ✅ |
| `/meals` 等 | M4b | ✅ |
| `/finance` | M5 | ❌ |
| `/videos` | M6 | ✅ |
| `/ai` | M7 | ❌ |
| Export API / settings export | M8 | ❌ |

## activity_logs イベント登録簿

| event_type（実装） | MS | 記録 | History UI | テスト |
|---|---|---|---|---|
| `matrix_item_completed` | M1 | ✅ | ✅ | `MatrixCellItemTest`, `HistoryTest` |
| `matrix_item_reopened` | M1 | ✅ | ✅ | 同上 |
| `routine_session_completed` | M3 | ✅ | ✅ | `RoutineSessionTest`, `HistoryTest` |

- 不変ログ（取り消しは reopen イベント追加）。既存イベントは更新しない
- docs 表記 `routine_completed` とのドリフトは docs 側の追随が必要

## Laravel Boost との関係

導入状況と使いどころは [dev/laravel-boost.md](./dev/laravel-boost.md) を参照。

恩恵が大きい残作業（バックエンド縦断）:

| MS | Boost が効く理由 |
|---|---|
| M4 残り | 週次平均・期間集計を Tinker / Database Query で実データ検証 |
| M5 | 金額集計の正しさをクエリ単位で確認 |
| M6 仕上げ | Object Storage・署名付き URL を search-docs で版正確に参照 |
| M8 | list-routes / API Resource / 認証をドキュメント参照しながら設計 |
| 横断 | activity_logs 不変条件を実装後に Tinker で検証 |

純粋な Vue / UI 忠実化では Boost の出番は少ない（PHP 側ツールのため）。

## 推奨する次の着手順

1. **M4 残り** — 集計クエリ（週次平均等）と筋力チャート配線。Boost の検証ループが最も効く
2. **M2** — メモ / 振り返り（Export・AI の入力データにもなる）
3. **M5** — Finance（スコープ #7 確定後）
4. **M6 仕上げ** — 制限・非同期削除・サムネ
5. **M8** — Export（存在するモジュールから段階的でも可）
6. **M7** — AI（データが揃ってから）

## ドキュメントドリフト（要追随）

| 項目 | docs | 実装 | 推奨アクション |
|---|---|---|---|
| チャートライブラリ #1 | 未決定 | ECharts 導入済 | roadmap / records.md を更新 |
| ルーティン完了イベント | `routine_completed` | `routine_session_completed` | docs を実装名に合わせる |
| Phase 2 routine スキーマ | `tables.md` 旧ドラフト | ADR-0007 | tables.md に superseded 注記 |
| M0 装飾 PNG | assets.md に配置済と記載 | 実ファイル欠落 | 追加 or 記載修正 |
