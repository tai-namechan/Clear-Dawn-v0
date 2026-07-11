> アーカイブ: develop@`8d695bf` 時点のプロダクト現状サマリーであり、現在仕様ではない。

# Seed K Personal OS — プロダクト現状サマリー（外部レビュー用）

作成日: 2026-07-11 / ブランチ: develop（`8d695bf`）/ 全数値はコード・実行結果から採取
用途: 外部AIレビュアーへの共有。単体で読めるよう自己完結で記述。

---

## 1. プロダクト概要

個人開発の「Personal OS」。3つのプロダクトが1つのLaravelアプリ内に同居し、Product Switcherで切り替える。

| プロダクト | 役割 | 一言 |
|---|---|---|
| **Clear Dawn** | 未来 | 人生の方向性・目標をマトリクスとルーティンで管理 |
| **ヨユウ** | 現在 | 「焦らず、前へ回す」AI秘書。今日の予定・タスク・朝ブリーフィング・チャット |
| **キオク** | 過去 | 思考・経験・失敗・学びを保存し、AI整理・検索・Recall（他プロダクトへの記憶注入）する個人記憶基盤 |

核となる体験ループ: 記録（キオク）→ AI整理 → Recall → ヨユウの秘書が過去の文脈を踏まえて今日を支援 → その行動がまた記録される。

## 2. 技術スタック

- PHP 8.4 / **Laravel 13**（framework ^13.17）/ PHPUnit 12 / Larastan 3 / Pint
- Inertia v3 ＋ **Vue 3** ＋ Tailwind CSS 4 / Wayfinder（型付きルートヘルパー生成）/ Vite
- 認証: Fortify（2FA・パスキー対応）
- DB: ローカルsqlite / 本番想定MySQL（未デプロイ）。Queue: database driver。Cache: database（cache_locksでロック共有）
- AI: Anthropic API直（SDK不使用、自前 `AiGateway`）。モデル2段: cheap=Haiku 4.5 / strong=Sonnet
- ホスティング予定: Laravel Cloud（Worker=Background Process）。**まだ一度もデプロイしていない**

## 3. アーキテクチャ

```
app/
├── Domain/
│   ├── Kioku/    Models(Memory, MemoryLink, Connector) / Services(KiokuSearchService,
│   │             RecallService, RelatedMemoryService, MemoryClassifier) /
│   │             Jobs(EnrichMemoryJob) / Types(9種のMemoryTypeDefinition)
│   ├── Yoyu/     Models(YoyuTask, YoyuFocusItem, YoyuBriefing, YoyuPlace) /
│   │             Jobs(GenerateYoyuBriefingJob) / Support(MockCalendar)
│   └── Shared/   AI(AiGateway, PromptTemplate) / Models(AiUsageLog, BelongsToUser trait)
├── Http/Controllers/  Clear Dawn系（Routine/Matrix/Metric等）+ Kioku/ + Yoyu/ + MealEntry
├── Services/          Clear Dawn系のユースケース単位Service（40個）
└── Console/Commands/  KiokuClassifyEvalCommand（分類プロンプト回帰eval）他
```

- **user_id分離**: `BelongsToUser` traitがKioku/Yoyuの全モデルにグローバルスコープを強制。`withoutUserScope()` 使用箇所は全て直後に明示的user_id条件あり（監査確認済み）
- **外部API原則**: AI呼び出しは必ずQueue Worker経由（Webプロセスでの同期実行は撤廃済み、後述）
- テーブル数35（users/cache/jobs系含む）。主要: memories, memory_links, ai_usage_logs, yoyu_briefings/tasks/focus_items, connectors(stub), life_areas, matrix_*, routine_*(9), metrics, food_items, meal_entries, nutrition_goals, videos, activity_logs

## 4. 実装状態（プロダクト別）

### Clear Dawn（最も成熟）
マトリクスボード（life_areas×行でセル管理）、ルーティンシステム（計画→セッション実行→ステップ記録、動画添付）、メトリクス記録・チャート（ECharts）、履歴、パフォーマンス管理（コンディション記録）、食事記録（food_items/meal_entries手動登録・PFC集計・目標）。AI連携はまだ無い。

### キオク（AIパイプライン完成）
```
保存(Web) → INSERT(status=captured) → dispatch()（transaction内はafterCommit()）
→ EnrichMemoryJob（Queue Worker / ShouldBeUnique / timeout=180 / tries=3 /
   条件付きUPDATEでclaim / classify結果即永続化でリトライ再課金なし / failed()セーフティネット）
   → classify v2（Haiku, 9type定義＋日本語強制）→ extract（type別プロンプト, error_log/decisionはSonnet）
   → status=ready → 関連記憶を再計算（直近100件からタグ・タイトル語スコアリング, memory_links保存）
```
- 検索: user_id絞り→ title/summary/raw_content の `LIKE '%kw%'`（上限50件）＋type/tag/期間フィルタ
- Recall: 検索上位15→フィルタ→5件（各summary or 原文200字）を他プロダクトのプロンプトへ注入
- 再整理ボタン（ready/failedのみ条件付きリセット）、分類回帰eval（fixture 8ケース、実APIコマンド）
- UI: washi（和紙）パレット、type/source件数、詳細画面に構造化データ表示

### ヨユウ（骨格あり・入力がモック）
- Today/Tasks/Mind/Chatタブ。タスクCRUD、マインドダンプ（→キオクへも保存）、AIチャット（Sonnet, Recall5件＋未完了タスク注入, 履歴30往復上限）
- 朝ブリーフィング: Queue Job化＋status列＋フロントポーリング（`useYoyuBriefingPoll`）まで完成。**ただし入力の予定とClear Dawnの一手は `MockCalendar` の固定値**

### 横断
- `AiGateway`: 全AI呼び出しの単一入口。月次上限（既定$10/user）チェック＋ai_usage_logsへ明細記録＋モデル別単価で概算コスト算出

## 5. 品質状態（実測）

- **テスト: 234件 / 1,173 assertions 全通過**（Feature中心。Queue経由の統合テスト含む: jobsテーブル登録→queue:work→ready遷移、afterCommitのロールバック検証、二重dispatch排除、AI失敗時の原文保持等）
- Pint: 既知の違反1件のみ（`TodayResource.php`、旧コミット由来）
- PHPStan(Larastan lv不明): 既知18件（nullsafe過剰・Collection共変性など軽微。新規コードはクリーン）
- E2E/ブラウザテストなし。CI設定は未確認

## 6. 直近の主要な意思決定と修正（1週間以内）

1. **Critical修正済み**: `dispatch()->afterResponse()` が実はdispatchSync（Webプロセス同期実行）でAI処理2×60秒がFPMワーカーを占有していた → Queue Worker経由へ全面移行（PR #97相当）
2. **分類品質**: 「黒染めを忘れた」が `error_log`（開発エラー用type）に誤分類 → classifyプロンプトv2（全typeの1行定義＋error_logを技術作業に限定＋title/tagsの原文言語強制）＋回帰eval導入
3. **スケーラビリティ監査完了**（`docs/kioku-scalability-audit-and-business-model.md` v1.1）: 主な残課題は
   - F-2: AI利用上限判定が毎回SUM＋TOCTOU競合 → 予約・確定・解放の3段階＋usage_request_id設計済み・未実装
   - F-4: Recallがヨユウ表示ごとにLIKE走査 → SQL側フィルタ＋キャッシュ設計済み・未実装
   - LIKE検索は1ユーザー約10万件でp95目標割れ見込み（SQLite 1M行実測40ms@1万行/人から線形外挿。本番MySQL再計測前提）
   - invariant明文化: `memory_type !== null`＝classify完了。手動分類導入時は `classified_at` 等へ移行必須
4. **事業モデル**: 月額＋AIクレジット枠（整理=1、チャット=3〜10重み）＋保存は広く許可。原価はストレージでなくAI呼び出しに支配される実測に基づく

## 7. 未実装の残作業とPhase 1計画（`docs/design/ai-features-completion-design.md` 参照）

| PR | 内容 | 状態 |
|---|---|---|
| PR-A | F-2予約制＋AI利用量表示画面（ai_usage_monthly/ai_usage_requests） | 設計済み |
| PR-B | キオク一覧の自動更新（軽量statusエンドポイント＋バックオフポーリング） | 設計済み |
| PR-C | Google Calendar連携（socialite/OAuth readonly/暗号化token/イベントキャッシュ/CalendarProvider interface） | 設計済み・依存承認待ち |
| PR-D | ブリーフィングv2（実予定＋Clear Dawn実データ＋PHPでの空き時間計算・余裕メーター＋構造化JSON出力） | 設計済み |
| → | **ここで初のテスト環境デプロイ**（Laravel Cloud） | |
| 以降 | Recall改善(F-4) → バーコード/成分表OCR食事登録 → ChatGPTエクスポート取込（RAG基盤: chat_conversations/memory_chunks/sensitivity 0-4/マスキング/ai_requests送信ログ。Phase Aはembeddingなし、pgvector判断は遅延） | 設計済み |

やらないと決めたもの（当面）: Gmail、LINE通知、出発時間計算、Maps、pgvector、WebSocket、Slack/GitHubコネクタ。

## 8. 既知のリスク・弱点（自己申告）

1. **未デプロイ**: 本番相当環境（MySQL・Worker常駐・実レイテンシ）での動作実績ゼロ。EXPLAIN実測もSQLiteのみ
2. F-2競合が未修正のまま（上限際で数十%超過し得る）— PR-Aで解消予定
3. Recallの毎表示LIKE走査＋Recall品質の計測基盤（recall_logs）未着手
4. 検索は日本語FULLTEXT未対応（LIKE。数万件/人までの想定）
5. ヨユウchatの会話履歴はクライアント保持（リロードで消える）。会話永続化なし
6. sensitiveフラグはRecall除外のみ（保存時のAI整理では原文がAnthropicへ送信される — 事業ドキュメントに明記済み、完全ローカル化は将来）
7. モバイル実機・アクセシビリティの検証なし

## 9. レビューで特に意見が欲しい点

1. Phase 1のPR分割・順序は妥当か（特にPR-C/Dの粒度）
2. ai_usage_monthly予約制の設計（§設計書2.4）に競合の穴がないか
3. ChatGPTインポート基盤のスキーマとPhase A（embeddingなしRAG）で実用に足りるか
4. LIKE→FULLTEXT ngram→検索基盤分離という段階戦略の移行トリガー設定
5. 3プロダクト同居（単一Laravel＋Inertia）を続ける限界点はどこか

## 10. 参照ドキュメント（リポジトリ内）

- `docs/design/ai-features-completion-design.md` — Phase 1〜3の詳細設計（本書§7の展開）
- `docs/kioku-scalability-audit-and-business-model.md` — 性能監査＋事業モデル（v1.1）
- `docs/dev/2026-07-11-kioku-queue-classify-worklog.md` — 直近の修正経緯
- `CLAUDE.md` — 開発規約（docs/が仕様の正、Laravel Boostガイドライン）
