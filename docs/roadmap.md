# 実装ロードマップ

v0 の実装をマイルストーン（MS）単位で管理する。カレンダー日程では管理しない。
各マイルストーンは「Route → Controller → Query/Service → Vue → テスト」の縦断で完結させる。

**実装の現在地（done / partial / not_started）は [progress.md](./progress.md) を正とする。**  
AI 開発支援（Laravel Boost）は [dev/laravel-boost.md](./dev/laravel-boost.md) を参照。

## Phase と Milestone の対応

| Phase | Milestone | 主な機能 | 実装状態 |
|---|---|---|---|
| — | M0 | docs 整備 + デザイン基盤 | partial |
| Phase 1 | M1 | TOP Matrix、領域管理、activity_logs（M1 記録開始） | done |
| Phase 1.5 | M2 | 日次・週次振り返り（汎用メモはキオクへ移管・凍結） | not_started |
| Seed K | SK1〜 | Personal OS 切替 → キオク P0/P1 → ヨユウ → Recall | SK1 in progress |
| Phase 2 | M3 | ルーティン / トレーニング、実行履歴 UI（/history） | done |
| Phase 2.5 | M4 | 記録（体重・睡眠・筋力・野球）、グラフ | partial |
| Phase 3 | M5 | Finance | not_started |
| Phase 3.5 | M6 | 動画 | partial |
| Phase 4 | M7 | AI 支援 | not_started |
| Phase 4.5 | M8 | Export API、v1 移行リハーサル | not_started |

M0（docs 整備 + デザイン基盤）は Phase 番号の外で先行する。

## マイルストーン

| MS | 内容 | 前提・判断ポイント |
|---|---|---|
| M0 | docs 整備 + デザイン基盤（`app.css` トークン反映、AppLayout / Sidebar / 背景素材、フォント導入） | フォント配信方法・ダークモード方針を確定（→ 未決定 #2, #10） |
| M1 | TOP Matrix 縦断（Matrix 中核 4 テーブル + **activity_logs** migration、Dashboard、セル編集モーダル、領域管理、Policy / FormRequest / Query / Service、Feature テスト） | v0 の核。[top-matrix.md](./product/screens/top-matrix.md) を正とする。**activity_logs のテーブル設計と記録開始を M1 で行う**（`matrix_item_completed` / `matrix_item_reopened` のみ） |
| M2 | 日次・週次振り返り（メモはキオク移管） | 汎用メモは作らない。[seed-k-personal-os.md](./product/seed-k-personal-os.md) 参照。振り返りは `completed_at` 参照から開始可 |
| SK1 | ProductSwitcher + `/yoyu` `/kioku` プレースホルダ | 薄い切替シェルのみ。Clear Dawn 本体は止めない |
| SK2 | キオク P0（保存・一覧・検索） | memories + Console。Recall は interface のみ可 |
| SK3 | キオク P1（AI 整理・スキーマレジストリ） | 同期 AI ゼロ。Queue で enrich |
| SK4 | ヨユウ Today | `yoyu_focus_items` 等はヨユウ設計確定後 |
| SK5 | ヨユウ秘書 × Kioku Recall | 二層コンテキスト |
| M3 | ルーティン / トレーニング + **実行履歴 UI（/history）** | 実装イベント名は `routine_session_completed`（roadmap 旧称 `routine_completed`）。ルーティンの TOP 補助表示可否を判断（→ 未決定 #4） |
| M4 | 記録（体重・睡眠・筋力・野球）+ グラフ | チャートは **ECharts 導入済**（→ 未決定 #1 は実質クローズ候補）。記録系スキーマ確定（→ 未決定 #3）。残: 週次平均等の集計・筋力チャート UI |
| M5 | Finance | スコープ確定（→ 未決定 #7） |
| M6 | 動画（Laravel Cloud Object Storage、署名付き URL） | コア（署名付き upload/stream）は実装済。ローカルは MinIO 検討。サイズ・尺の上限確定 |
| M7 | AI 支援 | プロバイダ・コスト・形態・ログ保存を確定（→ 未決定 #6） |
| M8 | Export API + v1 移行リハーサル | 認証方式確定（→ 未決定 #8）。allowlist 契約は [export-api.md](./api/export-api.md) |

横断タスク（マイルストーン外・着手は別途指示があってから）:

- Sentry 導入（production のみ）
- test 環境の追加（最初は dev / prod のみ）
- スマホ PWA 対応の検討

## activity_logs の M1 / M3 分担（確定）

| 項目 | タイミング | 内容 |
|---|---|---|
| テーブル設計 + migration | **M1** | activity_logs テーブルを Phase 1 で作成 |
| 記録開始 | **M1** | セル項目完了切替時に `matrix_item_completed` / `matrix_item_reopened` を記録 |
| 実行履歴 UI | **M3** | GET /history で activity_logs を時系列表示 |
| ルーティンイベント | **M3** | セッション完了時に `routine_session_completed` を追加（実装名。旧称 `routine_completed`） |

- activity_logs は **不変のイベントログ** である。TOP Matrix 自体のスナップショット履歴ではない
- 完了取り消しは `matrix_item_reopened` イベントを **追加** する（既存イベントは更新・削除しない）

## 未決定事項

決定したら該当ドキュメントを更新し、必要に応じて ADR を追加する。

| # | 事項 | 選択肢・論点 | 決定期限 |
|---|---|---|---|
| 1 | チャートライブラリ | **実装で ECharts を採用済**（`echarts`）。ADR 化して正式クローズするか判断 | M4（実質決定済） |
| 2 | フォント配信 | セルフホスト（public/fonts）or CDN。プライバシーと表示安定性ならセルフホスト推奨 | M0 |
| 3 | 記録系スキーマ | 汎用テーブル一本 vs ハイブリッド（推奨: ハイブリッド） | M4 設計時 |
| 4 | ルーティンの TOP 表示 | 当日実施予定を TOP に補助表示するか、完全独立か | M3 設計時 |
| 5 | ~~実行履歴の範囲~~ | **確定**: activity_logs でマトリクス完了 + ルーティン完了を統合。テーブル設計・M1 記録開始、UI は M3 | — |
| 6 | AI 支援の形態 | 提案パネル埋め込み vs 独立チャット画面。プロバイダ・API キー管理・コスト | M7 前 |
| 7 | Finance のスコープ | 簡易収支のみ vs 予算・資産管理まで | M5 前 |
| 8 | Export API の認証 | トークン認証（Sanctum 等・package 構成確認要）vs 画面ダウンロードのみ | M8 前 |
| 9 | matrix_rows の可変化 | v0 は固定 3 行のまま（推奨）。将来ユーザー編集可否 | v1 検討 |
| 10 | ダークモード / PWA | **確定**: v0 はライトモード固定。Appearance Settings は提供しない。PWA は v0 対象外 | — |
| 11 | 中間幅レイアウト | サイドバー折りたたみの閾値、最小対応幅 | M0〜M1 |
| 12 | ~~メモの Markdown 対応~~ | **確定**: 汎用メモはキオクへ移管。CD に memos は作らない | — |
| 13 | 振り返りの完了実績参照 | M2 初期は completed_at。activity_logs 参照への移行時期 | M2 設計時 |

## 各マイルストーンの完了条件（共通）

- 該当画面が [design-system.md](./design/design-system.md) のトークンで実装されている
- [ui-quality-checklist.md](./design/ui-quality-checklist.md) を通過している
- Policy による認可と `user_id` スコープの Feature テストがある
  （権限外データが返らない・操作できないことをアサート）
- Lint（Pint / ESLint）と静的解析（PHPStan / vue-tsc）で変更ファイルにエラーがない
- 仕様と実装が乖離した場合、docs 側を先に更新している
