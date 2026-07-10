# 実装進捗（v0）

> 最終更新: 2026-07-10  
> 正: [roadmap.md](./roadmap.md) のマイルストーン定義。本ファイルは **実装の現在地** を可視化する。

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
| SK1 | Seed K | ProductSwitcher + プレースホルダ | **partial** | 切替シェル実装済。プレビュー実データ化は後続 |
| SK2 | Seed K | キオク P0 | **not_started** | memories + 保存/一覧/検索 |
| SK3 | Seed K | キオク P1 | **not_started** | Queue AI 整理 |
| SK4 | Seed K | ヨユウ Today | **not_started** | データ設計確定後 |
| SK5 | Seed K | ヨユウ秘書 × Recall | **not_started** | 二層コンテキスト |

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
