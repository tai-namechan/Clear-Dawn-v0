# Clear Dawn ドキュメント

Clear Dawn v0（プロトタイプ）の仕様・設計・デザインに関するドキュメント群。

> 夜明け前の静けさの中で、今日やるべきことを決める。

## 目次

### プロダクト仕様（docs/product/）

| ドキュメント | 内容 |
|---|---|
| [overview.md](./product/overview.md) | プロダクト概要・コンセプト・v0 スコープ |
| [seed-k-personal-os.md](./product/seed-k-personal-os.md) | Seed K Personal OS（切替・キオク/ヨユウ境界の確定事項） |
| [kioku-quick-capture.md](./product/kioku-quick-capture.md) | キオク クイックキャプチャ／音声入力（30秒raw保存・保存先行・IndexedDBキュー） |
| [kioku-final-remaining-implementation.md](./product/kioku-final-remaining-implementation.md) | キオク残実装（OpenAI実文字起こし + シオリ/ナギのコンシェルジュ手紙実験） |
| [kioku-concierge-daily-pilot.md](./product/kioku-concierge-daily-pilot.md) | コンシェルジュ14日日次pilot → 週次、sensitive halt、test/preview、評価指標 |
| [information-architecture.md](./product/information-architecture.md) | 情報設計・サイドバー構成・全画面一覧 |
| [screens/top-matrix.md](./product/screens/top-matrix.md) | TOP Matrix 詳細仕様（v0 の核・Phase 1 対象） |
| [screens/life-areas.md](./product/screens/life-areas.md) | 領域管理（Life Area）仕様 |
| [screens/memos.md](./product/screens/memos.md) | メモ仕様 |
| [screens/reviews.md](./product/screens/reviews.md) | 日次・週次振り返り仕様 |
| [screens/routines.md](./product/screens/routines.md) | ルーティン / トレーニング / 実行履歴仕様 |
| [screens/records.md](./product/screens/records.md) | 記録（体重・睡眠・筋力・野球）とグラフ仕様 |
| [screens/finance.md](./product/screens/finance.md) | Finance 仕様 |
| [screens/videos.md](./product/screens/videos.md) | 動画仕様 |
| [screens/ai-assist.md](./product/screens/ai-assist.md) | AI 支援仕様 |

### デザイン（docs/design/）

| ドキュメント | 内容 |
|---|---|
| [design-system.md](./design/design-system.md) | デザイントークン・フォント・アイコン・コンポーネント規約 |
| [ui-quality-checklist.md](./design/ui-quality-checklist.md) | 実装時の UI 品質チェックリスト |
| [assets.md](./design/assets.md) | 素材ディレクトリ・命名・変換ルール |
| [ai-features-completion-design.md](./design/ai-features-completion-design.md) | AI機能の完成設計（ヨユウ／キオク／食事／取込） |
| [ai-features-implementation-plan.md](./design/ai-features-implementation-plan.md) | AI機能の実装計画（PR分割） |
| [kioku-foundation-goal-gap.md](./design/kioku-foundation-goal-gap.md) | キオク基盤: 現状（QC済み）/ 設計予定 / やりたいことの差分・二段トリガー |

### データ設計（docs/data/）

| ドキュメント | 内容 |
|---|---|
| [er-overview.md](./data/er-overview.md) | 全体 ER 図と横串 FK 方針 |
| [tables.md](./data/tables.md) | テーブル定義（Phase 1 確定分 + 後続ドラフト） |
| [conventions.md](./data/conventions.md) | ULID / user_id スコープ / soft delete / 日付規約 |

### API（docs/api/）

| ドキュメント | 内容 |
|---|---|
| [export-api.md](./api/export-api.md) | v1 移行用 Export API 仕様 |

### 計画・意思決定

| ドキュメント | 内容 |
|---|---|
| [roadmap.md](./roadmap.md) | 実装ロードマップ（マイルストーン管理） |
| [progress.md](./progress.md) | 実装進捗の可視化（done / partial / not_started） |
| [dev/laravel-boost.md](./dev/laravel-boost.md) | Laravel Boost 導入・使いどころ |
| [architecture/frontend-asset-boundaries.md](./architecture/frontend-asset-boundaries.md) | フロントエンド・アセット境界（ページ追加耐性・static closure 予算） |
| [architecture/kioku-knowledge-retrieval.md](./architecture/kioku-knowledge-retrieval.md) | キオク ナレッジ検索・Context Builder 基盤（三層モデル・タグ検索・AI向け取得上限） |
| [adr/](./adr/) | アーキテクチャ決定記録（ADR） |

## ドキュメントの正（Source of Truth）

| 領域 | 正 |
|---|---|
| TOP Matrix の仕様・設計 | Phase 1 仕様書・設計書 v0.3 の内容を移植した [screens/top-matrix.md](./product/screens/top-matrix.md) |
| デザイン | UI 仕様書 v2 の内容を移植した [design-system.md](./design/design-system.md) |
| デザイントークンの実装 | `resources/css/app.css`（実装後） |
| 確定した設計判断 | [adr/](./adr/) |
| 目標・プログラム・ロードマップ | [screens/goals.md](./product/screens/goals.md) / [screens/programs.md](./product/screens/programs.md) |
| 今日/作戦画面・ルール/推奨 | [screens/today-ops.md](./product/screens/today-ops.md) / [ADR-0011](./adr/0011-five-data-kinds-and-rule-engine.md) |

## 更新ルール

- 仕様変更は該当ドキュメントを更新してから実装する（ドキュメント先行）
- 設計上の意思決定（技術選定・データ方針など）が確定したら ADR を追加する
- 「未決定」と明記された項目は、決定するまで実装で先取りしない
- 本リポジトリは public のため、実データ・秘密情報・個人情報・認証情報は一切記載しない
