# 作業記録 2026-07-11: キオクQueue正常化・分類改善（PR1＋分類v2）

ブランチ: `cursor/product-switcher-shell-c23e` / push済み: `dcd7244..3f85b0b`

## 今回pushした3コミット

| コミット | 内容 |
|---|---|
| `2b3f657` fix: process memory enrichment through queue worker | **PR1**。`dispatch()->afterResponse()`（実体はdispatchSync＝Webプロセス同期実行）を廃止。トランザクション外は `dispatch()`、`DB::transaction` 内（Yoyu storeFocus）は `dispatch()->afterCommit()`。EnrichMemoryJobの冪等化: `ShouldBeUnique`（memory ID一意）、条件付きUPDATEによるstatus claim（captured→enriching、リトライ時のみenriching再claim可）、`$timeout=180` 明示、`failed()` セーフティネット、classify結果の即時永続化でリトライ時の再課金防止。検証テスト7項目付き |
| `4cca878` docs: document memory classification completion invariant | スケーラビリティ監査＋事業モデル文書（v1.1）。**invariant**: `memory_type !== null`＝分類完了は現時点のみ成立。種類の手動指定またはコネクタ事前分類を導入する際は `classified_at` / `enrichment_stage` へ移行すること |
| `3f85b0b` fix: stop classifying everyday mistakes as error_log, add re-enrich and eval | 「黒染めを忘れた」がerror_log誤分類された問題。classifyを `MemoryClassifier`（prompt `classify.v2`）へ抽出し、全typeの1行定義＋error_logの範囲限定（技術作業のみ）＋「titleとtagsは原文と同じ言語で」を追加。`POST /kioku/memories/{memory}/reenrich`（ready/failedのみ条件付きリセット、invariantに従いmemory_typeをnull化）＋詳細画面「AIで再整理」ボタン。回帰eval `php artisan kioku:eval-classify`（fixture 8ケース、実API・課金確認付き） |

## リモートforce push対応（rebase）

push時にリモートが別セッションによりforce update済みと判明（Kioku UIリスタイル、件数表示、chat履歴上限、`failed()`フック等）。force pushはせず、3コミットをリモート先端 `dcd7244` へrebaseで積み直した。

- 衝突解決: `Detail.vue`（リモートのwashiリスタイルを採用し再整理ボタンをそのデザインで再適用）、`routes/kioku.php`（リモートのsources件数＋私のreenrich両方採用）
- リモートと私が**同内容の `failed()` を別々に追加**していたため自動マージが重複メソッドを生成 → 中間コミット単位でも壊れないよう履歴を作り直して解消（全コミットで `php -l` / `function failed` 1件を確認）
- テスト規約をリモートに合わせた（`$this->anthropicFakePattern()`、TestCase一括の `preventStrayRequests`）
- 最終ツリーはrebase初回解決版と完全一致を確認（`git diff backup-tip HEAD` 空）

## 「不要なコードをコミットしていないか」の監査結果

3コミットの全ファイルを確認済み。**混入なし**。

- コード: EnrichMemoryJob / MemoryController / HomeController / MemoryClassifier / KiokuClassifyEvalCommand / routes / Detail.vue — すべて今回の目的に対応
- テスト・fixture: MemoryTest追加分＋eval用JSON 2点（意図した成果物）
- ベンチマークスクリプト・生成DBはscratchpadのみで実行し**リポジトリに含めていない**（監査プロンプトの指示どおり）
- Wayfinder生成物（resources/js/routes）はgitignore対象のためコミット外（ビルド時に `composer` スクリプトで再生成）
- pint失敗が1件出るが対象は `app/Http/Resources/TodayResource.php` — 旧コミット `f505ad7` 由来の既存問題で今回のコミットには含まれない

## 検証結果

- phpunit: **231/231 通過**（rebase後、リモート追加分含む）
- vue-tsc / eslint（Detail.vue）: クリーン
- pint: 今回変更ファイルはクリーン（上記TodayResourceの既存指摘のみ）

## ローカルに残した未コミット物（別セッション由来・判断は持ち主に委ねる）

- `stash@{0}` pre-rebase-other-session-files: docs/adr/README.md・docs/data/tables.md・docs/roadmap.md の編集（リモート版と内容が競合するため未適用。必要なら `git stash pop` して手動解決）
- 未ステージ: docs/product/information-architecture.md, docs/product/screens/records.md, package-lock.json
- 未追跡: .claude/settings.json, docs/prompts/

## 次のアクション

1. test環境へデプロイ
2. Laravel Cloud: `CACHE_STORE=database` / `QUEUE_CONNECTION=database` を確認
3. Background Process `php artisan queue:work --sleep=3 --tries=3 --timeout=180` がRunning
4. スモークテスト: 保存 → captured → enriching → ready（capturedのまま滞留＝Worker未起動）
5. 実APIで `php artisan kioku:eval-classify`（8ケース・Haiku数円）を実行し、黒染めケースがthought/eventに落ちることを確認
6. 既存の誤分類データは詳細画面「AIで再整理」で修正
7. 次PR: F-2（AI利用枠の予約・確定・解放＋usage_request_id）→ F-4（Recall改善）
