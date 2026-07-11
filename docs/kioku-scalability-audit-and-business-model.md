# キオク基盤 大規模データ監査 & 事業・料金モデル設計（Fable 5 統合回答）

作成日: 2026-07-11 / 改訂: 2026-07-11（レビュー反映 v1.1）
根拠: 実コード読解（Migration / Model / Service / Job / Controller / Resource / Vue / config）＋ ローカルSQLiteでの1万・10万・100万件実測ベンチマーク。
本番想定DBはMySQL（migrationコメントより）。ベンチはSQLiteだが、`LIKE '%...%'` の計算量特性（ユーザー行の全走査）はエンジン非依存。

**v1.1での主な訂正（レビュー指摘の反映）**
- F-1の修正方針を「一律dispatch()」から「トランザクション外はdispatch() / 内はafterCommit()」へ訂正。**F-1・F-5はPR1として実装済み**（2026-07-11、テスト7項目付き）。
- F-2の修正案を単純なカウンタ更新から**予約・確定・解放の3段階＋usage_request_id**へ訂正（カウンタ更新だけではAPI実行前の上限突破を防げない）。
- ベンチ数値の位置づけを訂正: **確定できたのは「LIKEが線形劣化すること」のみ**。何件で目標を超えるかは本番相当MySQLで要再計測。「数億件でも一覧維持可能」も撤回（インデックスサイズ・キャッシュヒット率・バックアップ時間等の別問題が出る）。
- 料金プランの枠を訂正: 旧案のPersonal ¥980（整理500＋チャット300）は**枠上限利用時にAI原価だけで逆転赤字**。チャットと整理を同価値にせず、AIクレジット制へ。具体枠は利用ログ2〜4週間の実測後に確定。
- プライバシー表現を訂正: Anthropic通常APIは**標準で最大30日保持**（Zero Data Retentionは別途契約の例外）。sensitiveフラグは**Recall除外のみ**で、AI整理時には原文がAnthropicへ送信される — 「AIに渡らない」とは宣伝不可。

---

# 第1部: スケーラビリティ監査

## 1. 総合判定

**即時修正が必要（2件）＋ 検索・Recallは1ユーザー数十万件で設計変更が必要**

- 「大規模データ対応済み」とは判定できない。
- ただし全経路に `user_id` スコープ（`BelongsToUser` グローバルスコープ）と件数上限が入っており、**骨格は健全**。致命傷は「Queueを実質使っていないこと」と「検索方式」の2点に集中している。
- 一覧・ステータス系のインデックス検索は1ユーザー数万件規模なら成立する見込み。ただし総件数が数億件に達すると、クエリ自体が速くてもインデックスサイズ・キャッシュヒット率・書き込み増幅・バックアップ時間という別の制約が現れるため、「数億件でも一覧は維持可能」とは言わない（v1.1訂正）。検索・Recallの劣化は総件数ではなく**ヘビーユーザー1人の件数**に連動する（後述の実測根拠）。

## 2. データ規模別評価

前提: 総件数に対しユーザー数100〜1万、大口ユーザーは総数の10〜30%を占有するケースを含む。

| データ量 | 一覧 | 検索 | Recall | Queue | ログ集計 | 総合評価 |
|---|---|---|---|---|---|---|
| 100万件（〜1万件/人） | ○ | △（LIKE走査 40ms級） | △（毎PVで走査＋5 UPDATE） | △（PR1でQueue化済み。滞留・公平性制御は未） | ○ | PR2/PR3完了で運用可 |
| 1,000万件（〜10万件/人） | ○ | ×（数百ms〜秒級） | ×（Yoyuホームが遅延） | ×（修正前提） | △（月次SUMが重く） | 検索方式の変更が必要 |
| 1億件（〜100万件/人） | △（cursor化必要） | ×（秒〜数十秒） | × | 要専用Worker群 | ×（集約テーブル必須） | FULLTEXT/検索基盤＋集約なしでは運用不可 |
| 10億件 | ×（単一テーブル限界） | × | × | × | × | パーティション＋検索分離＋原文外部化が前提 |

## 3. Finding一覧

### F-1【Critical・PR1で解消済み】AI整理がQueueではなくWebプロセスで同期実行される

- 対象: [MemoryController.php:79](app/Http/Controllers/Kioku/MemoryController.php#L79)、[HomeController.php:138](app/Http/Controllers/Yoyu/HomeController.php#L138)
- コード（修正前）: `EnrichMemoryJob::dispatch($memory->id)->afterResponse();`
- 挙動: Laravel 13では `afterResponse()` はレスポンス終了時に `dispatchAfterResponse()` → 最終的に `dispatchSync()` となり、**Queueに積まず同一PHPプロセスで同期実行**する。EnrichMemoryJobはAI APIを2回呼び（classify＋extract、各タイムアウト60秒、リトライ3回）、その間Webワーカー（FPMプロセス）を占有する。
- データ増加時の影響: 保存が同時に数十件来るとWebワーカープールが枯渇し、**キオクだけでなくClear Dawn・ヨユウ含む全ユーザーの全画面が応答不能**になる。「保存APIはAI処理を待たずに応答」は満たすが「Queue滞留時もユーザー操作がブロックされない」を根本的に満たさない。設計ドキュメント上の `原文保存→Queue→AI整理` フローが実装されていない。
- 発生規模: 同時保存 ≒ Webワーカー数（典型8〜32）で即発生。データ量に関係なく**今日でも起きる**。
- 修正（**PR1で実装済み**）: 単純な `afterResponse()` 削除ではなく文脈で分岐する。
  - トランザクション外（MemoryController::store）→ `dispatch()`
  - `DB::transaction` 内（Yoyu HomeController::storeFocus）→ `dispatch()->afterCommit()`（コミット前にWorkerがJobを取得する競合を防ぐ。Laravel公式の案内どおり）
- 副作用: worker常駐（Supervisor等）が運用上必須になる。
- 検証: 保存レスポンス時点でAI未実行 / jobsテーブル登録 / `queue:work` 後にready / afterCommitがロールバック時に未登録、をテストで確認済み。

### F-2【Critical】AI利用上限判定にTOCTOU競合（重複課金・上限突破）

- 対象: [AiGateway.php:87-99](app/Domain/Shared/AI/AiGateway.php#L87-L99) `assertWithinQuota()`
- 実行SQL: `SELECT SUM(estimated_cost_usd) FROM ai_usage_logs WHERE user_id=? AND created_at >= 月初` → チェック後にAPI呼び出し → 事後INSERT。
- 現在の挙動: チェックと記録の間にロックがない。複数Worker/リクエストが同時にチェックを通過すると、**全員が上限未満と判定して同時にAIを呼ぶ**。1回のEnrichで2回、chatも並行するため、上限際で数十%超過し得る。
- 加えてこのSUMは**AI呼び出しごとに毎回**実行される。インデックス `(user_id, created_at)` はあるが、ヘビーユーザーの月間ログが数万行になると呼び出しごとに数万行集計になる。
- 推奨修正（v1.1訂正）: 単純な `UPDATE ... SET spent = spent + actual_cost WHERE ...` では**実原価がAPI実行後にしか分からず、API呼び出し前の上限突破を防げない**。必要なのは予約・確定・解放の3段階:
  1. **予約**（API実行前）: `ai_usage_monthly` に `spent_usd` と `reserved_usd` を持たせ、
     `UPDATE ai_usage_monthly SET reserved_usd = reserved_usd + :estimated WHERE spent_usd + reserved_usd + :estimated <= :limit`
     が1行更新に成功した場合のみAIを呼ぶ（想定最大原価で予約）。
  2. **確定**（API成功）: 予約額を減らし実原価を `spent_usd` へ精算。
  3. **解放**（API失敗）: 予約額を戻す。
  - さらに同一AI呼び出しのリトライで二重確定しないよう **`usage_request_id` の一意制約**を付ける。
  - SUM廃止・競合解消・集計O(1)化も同時に達成される。明細ログは監査用に残す。PR2として実装予定。

### F-3【High】キーワード検索・Recallが `LIKE '%kw%'` で raw_content 全文を走査

- 対象: [KiokuSearchService.php:29-44](app/Domain/Kioku/Services/KiokuSearchService.php#L29-L44)
- 実行SQL（想定・MySQL）:
  ```sql
  SELECT * FROM memories
  WHERE user_id = ? AND status NOT IN ('archived')
    AND (title LIKE '%kw%' OR summary LIKE '%kw%' OR raw_content LIKE '%kw%')
  ORDER BY captured_at DESC LIMIT 50;
  ```
- 実測（SQLite、(user_id,captured_at)インデックスあり、ヒットなし＝最悪ケース、1ユーザー1万行時）:

  | 総件数 | 1ユーザー行数 | 検索(最悪) | 一覧100件 | status確認 |
  |---|---|---|---|---|
  | 1万 | 100 | 0.2ms | 0.5ms | 0.02ms |
  | 10万 | 1,000 | 3.5ms | 0.6ms | 0.02ms |
  | 100万 | 10,000 | **43ms** | 0.9ms | 0.03ms |

  計算量は**そのユーザーの行数×平均行サイズに線形**（user_idインデックスで絞った後、raw_content含む全行を読む）。
  **数値の位置づけ（v1.1訂正）**: この実測で確定できたのは「LIKEが線形劣化すること」まで。上の絶対値はSQLite・単発実行・合成データ・ウォームキャッシュの値であり、本番判断には使えない（本番はMySQL、raw_contentの実分布・バッファプール・同時実行数・ULID＋複数セカンダリインデックス容量・binlog/レプリカ/バックアップI/Oが異なる）。線形外挿すれば10万行/人で目標p95 500msを超える公算が高いが、**何件で超えるかは本番相当MySQLでの再計測が必要**（改善計画PR4に含む）。
- 日本語対応: FULLTEXT未使用。MySQL FULLTEXTはデフォルトで日本語を分かち書きできず**ngramパーサー指定が必須**。検索対象を title/summary に限定し raw_content を外すだけでも走査バイト数は1/10以下になる（現状は原文全体を毎回読む）。
- 良い点: 上限あり（50件）、空クエリはフィルタなし一覧にフォールバック、LIKE特殊文字はエスケープ済み。
- Recallへの波及: RecallServiceはこの検索をそのまま使うため、**ヨユウのホーム表示ごとに最悪ケースの走査が走る**（F-4）。

### F-4【High】RecallがYoyuホームGETごとに同期実行＋5件のUPDATE

- 対象: [RecallService.php:22-31](app/Domain/Kioku/Services/RecallService.php#L22-L31)、[HomeController.php:26,57](app/Http/Controllers/Yoyu/HomeController.php#L57)
- 現在の挙動: ホーム表示のたびに固定文言「今日の予定 余裕 タスク」でLIKE検索（k*3=15件取得→PHPでsensitive/statusフィルタ→5件）、さらに `$memory->increment('referenced_count')` を**ページビューごとに5回UPDATE**。
- 問題: (1) F-3のコストが最頻画面に乗る。(2) 表示しただけでreferenced_countが増え「参照された」の意味が壊れる（Lv3計測の毒になる）。(3) 読み取り画面が書き込みを伴い、将来のRead Replica分離を阻害。(4) sensitive/statusをPHP側でフィルタするため、上位15件が全部sensitiveなら候補0件になる（正しさの問題）。
- 良い点: AIへ渡す量はハード上限あり（5件×各200文字 or summary）。Claudeへ全件渡す構造ではない。再帰探索もない。
- 推奨修正: sensitive/status条件をSQLへ移動、Recall結果を数分キャッシュ、increment廃止（recall_logsテーブルへ非同期記録に置換）。

### F-5【High・PR1で解消済み】EnrichMemoryJobの冪等性・重複課金

- 対象: [EnrichMemoryJob.php](app/Domain/Kioku/Jobs/EnrichMemoryJob.php)
- 問題点（→ **PR1で以下すべて修正済み**）:
  - ステータス確認なしで処理開始（`status='ready'` でも再実行され再課金）→ 終端status即return＋`WHERE status='captured'` の条件付きUPDATEでclaim（0行なら他Worker取得済みとして終了、リトライ時のみ `enriching` 再claim可）。
  - 同一Memoryへの二重dispatchを防ぐ `ShouldBeUnique` なし → memory ID一意で追加。
  - extract失敗でリトライすると**classifyのAI課金が毎回再発生** → classify結果（memory_type/title/tags/importance）を成功直後に永続化し、リトライ時はclassify呼び出し自体をスキップ。
  - Jobに `$timeout` 未設定 → `$timeout=180` 明示。timeout killに備え `failed()` フックでstatus=failedを記録（raw_contentは保持）。
  - 良い点: payloadはID文字列のみ（Memory全体をシリアライズしていない）✓。DBトランザクション中に外部APIを呼んでいない ✓（storeFocusのトランザクションはdispatchのみ内包でAPI呼び出しは外）。

> **設計上の不変条件（invariant）**: 現時点では、`memory_type` は新規作成時に必ず `null` であり、`EnrichMemoryJob` の分類処理以外から書き込まれないため、`memory_type !== null` を分類完了判定として利用できる。
> ただし、将来「種類の手動指定・編集」または「コネクタ取り込み時の事前分類」を導入する場合は、この前提が崩れるため、`classified_at` または `enrichment_stage` などの専用カラムへ移行すること。
- 10万Jobが短時間投入された場合: database queueのjobsテーブル（インデックスはqueue列既定のみ）で単一workerなら1件2〜10秒として**数日分滞留**。ユーザー間の公平性制御なし（1ユーザーの大量登録が全体を先入れ先出しで詰まらせる）。failed_jobsは無制限蓄積。
- 推奨修正: handle冒頭で `status !== 'captured'/'enriching'` ならreturn、`WHERE status='captured'` 条件付きUPDATEで排他取得、classify結果をstructured_dataへ中間保存しリトライ時スキップ、`$timeout` 設定、キューをユーザー単位レート制限（`RateLimited` middleware）。

### F-6【Medium】memory_links の構造的不足

- 対象: [create_memory_links_table.php](database/migrations/2026_07_10_152219_create_memory_links_table.php)、[RelatedMemoryService.php](app/Domain/Kioku/Services/RelatedMemoryService.php)
- `to_memory_id` 単独インデックスなし（MySQLはFK暗黙インデックスで救済されるが、SQLite開発環境では逆方向検索が全走査）。`user_id` 列なし（今はfrom側のuser検証で担保、シャーディング時に分割キー不在で詰む）。自己参照リンク（from=to）を防ぐ制約なし。重複は `unique(from,to,kind)` で防止済み ✓。再帰探索はなし（深さ1固定）✓。取得上限3件 ✓。
- `cacheRelated()` は「最新100件」だけを候補にするため上限は健全だが、記憶が増えるほど**古い記憶と関連付かなくなる**（品質の天井）。また `forMemory()` がリンク未生成時に**GETリクエスト中にDELETE+INSERTを行う**（読み取り経路の書き込み、トランザクションなし）。

### F-7【Medium】一覧にページネーションがなく、巨大カラムを常に返す

- 対象: [MemoryController.php:31](app/Http/Controllers/Kioku/MemoryController.php#L31)（limit 100固定）、[MemoryResource.php](app/Http/Resources/Kioku/MemoryResource.php)
- `Memory::all()`・無制限`get()`は存在しない ✓。OFFSETページネーション自体が存在しないため深いページ問題もないが、**101件目以降に検索以外で到達できない**。Resourceが一覧でも `raw_content`（数KB）と `structured_data` を全件返すため、100件で数百KBのレスポンス。
- 推奨: `cursorPaginate()`（user_id, captured_at DESC, id DESC）＋一覧用の軽量Resource（raw_content除外）。`COUNT(*)`を伴う`paginate()`は使わないこと。

### F-8【Medium】インデックスの不備

- `index('user_id')` は複合3本と重複（書き込みコストだけ増える）→ 削除可。
- `(user_id, captured_at)` に `id` タイブレーカーなし＋ `ORDER BY captured_at DESC` のみ（同時刻データで順序不安定、cursor pagination導入の障害）。
- `(user_id, memory_type)` は `captured_at` を含まないため、type絞り込み一覧のソートでfilesortが発生し得る（LIMITがあるので実害は中）。
- `whereDate('captured_at', ...)` は `DATE(captured_at)` に展開され**インデックス無効**（[KiokuSearchService.php:62-68](app/Domain/Kioku/Services/KiokuSearchService.php#L62-L68)）→ `where('captured_at','>=',...)` へ。
- tags検索 `tags LIKE '%"tag"%'` はJSON文字列のLIKE走査（実測1M行で1.7ms/1万行ユーザー、線形悪化）→ 将来 `memory_tags` 正規化テーブル。

### F-9【Medium】ai_usage_logs に保持・集約・アーカイブ方針なし

- 明細は無限蓄積。月次集計テーブルなし（F-2の修正で同時解決）。パーティショニング・削除ポリシー未定義。ヘビーユーザー月3,000保存＝月6,000行、5年で36万行/人。

### F-10【Low】その他

- Yoyuの `YoyuTask` 取得が無制限 `get()`（[HomeController.php:29](app/Http/Controllers/Yoyu/HomeController.php#L29)）— タスクは自然に有界だが上限推奨。
- chatの `history` 配列に件数上限なし → トークン費用が会話長に比例して増大（[HomeController.php:216](app/Http/Controllers/Yoyu/HomeController.php#L216)）。
- ポーリング: **自動ポーリングは未実装**（Index.vueは手動リロードボタンのみ）。増幅リスクは現状なし。ただしリロードは `only:['memories']` で検索全体を再実行するため、将来は未完了IDのみの軽量statusエンドポイントへ分離（Echo/Reverb移行境界もそこに置ける）。
- ULID主キー（26文字文字列）は数値PKよりインデックスが大きいが、時系列ソート可能でシャーディングにも耐える。妥当。

### 良好な点（明示）

- **user_id分離は全経路で強制**: `BelongsToUser` グローバルスコープ＋`withoutUserScope()`使用箇所もすべて直後に `where('user_id', ...)` あり。Job内も `user_id` をMemoryから取得。ID直撃アクセスは404 ✓。memory_links経由の取得も `where('user_id', $memory->user_id)` で再検証済み ✓（[RelatedMemoryService.php:73](app/Domain/Kioku/Services/RelatedMemoryService.php#L73)）。
- AIへ渡す件数・文字数にハード上限あり（Recall 5件×200字、maxTokens指定）✓。
- 全件取得・`Memory::all()` なし ✓。
- Jobのpayloadは IDのみ ✓。

## 4. 現在のクエリフロー

```
【保存】 POST /kioku/memories （PR1修正後のフロー）
  INSERT memories (status=captured)
  → dispatch()（Yoyu storeFocusはトランザクション内のため dispatch()->afterCommit()）
  → 即レスポンス（status=capturedのまま。UIはcaptured/enrichingとも「整理中」表示）
  →（Queue Worker）EnrichMemoryJob（ShouldBeUnique, timeout=180, tries=3）:
      SELECT memories WHERE id=? → 終端status(ready/failed/archived)なら即return
      → 条件付きUPDATE status: captured→enriching（0行なら他Worker取得済み・終了）
      → assertWithinQuota: SUM(ai_usage_logs 当月) ← ★F-2（PR2で予約制へ）
      → AI classify (haiku) → INSERT ai_usage_logs → classify結果を即永続化（リトライ時再課金なし）
      → AI extract (haiku/sonnet) → INSERT ai_usage_logs
      → UPDATE memories (summary/structured_data/status=ready)
      → cacheRelated: SELECT 最新100件 → PHPスコアリング → DELETE+INSERT memory_links

【検索/一覧】 GET /kioku
  SELECT * WHERE user_id AND status NOT IN('archived')
    [AND (title|summary|raw_content LIKE '%kw%')×語数] ← ★F-3
  ORDER BY captured_at DESC LIMIT 100

【Recall】 GET /yoyu（毎表示）
  上記LIKE検索 LIMIT 15 → PHPでsensitive/readyフィルタ → 5件
  → UPDATE referenced_count ×5 ← ★F-4
  → 各件 summary or 原文200字 → プロンプト注入

【関連記憶】 GET /kioku/memories/{id}
  SELECT memory_links WHERE from_memory_id AND kind='related' LIMIT 3
  → SELECT memories WHERE user_id AND id IN(...)
  →（リンクなし時）cacheRelated を同期実行（GET中に書き込み）← F-6

【AI利用額判定】 全AI呼び出しの直前
  SUM(estimated_cost_usd) 当月 → 上限比較（ロックなし）← ★F-2
```

## 5. インデックス一覧

**現存（memories）**: `user_id`（重複・削除可）/ `(user_id,status)` ✓ステータス確認に最適 / `(user_id,memory_type)` △ソート列なし / `(user_id,captured_at)` ✓一覧の主力。
**現存（memory_links)**: `(from_memory_id,kind)` ✓ / `unique(from,to,kind)` ✓。
**現存（ai_usage_logs)**: `(user_id,created_at)` ✓月次集計に一致 / `(user_id,feature)`。

**不足**: `(user_id, captured_at DESC, id DESC)`（cursor用タイブレーカー）/ `(user_id, memory_type, captured_at)` / `to_memory_id`（明示）/ FULLTEXT ngram(title,summary)（導入時）/ jobsテーブルの滞留監視用の集計手段。

## 6. 段階的改善計画

| 段階 | 内容 |
|---|---|
| **今すぐ**（データ量無関係） | **PR1（実装済み）**: F-1 Queue正常化（dispatch / afterCommit分岐）＋F-5 Job冪等化（条件付きUPDATE claim・ShouldBeUnique・timeout・classify再課金防止）。**PR2**: F-2 予約・確定・解放の3段階＋usage_request_id。**PR3**: F-4 Recall改善（同期LIKE検索とreferenced_count++の廃止、sensitive/readyのSQL化、recall_logs、短期キャッシュ） |
| **〜100万件**（〜1万件/人） | cursorPaginate＋一覧軽量Resource（raw_content除外）。検索対象をtitle/summaryに限定するオプション。`whereDate`排除。重複index削除＋`(user_id,captured_at,id)`。Recall結果の短期キャッシュ。ユーザー別Jobレート制限 |
| **〜1,000万件**（〜10万件/人） | MySQL FULLTEXT ngram(title,summary) 導入（`KiokuSearchService`の内部差し替えで済む構造は既にある）。memory_tags正規化。ai_usage_logs月次ロールアップ＋明細の保持期間（例13ヶ月）。Queue専用DB or Redis。Read Replica検討 |
| **〜1億件** | 検索専用基盤（Meilisearch/Typesense/OpenSearch、Laravel Scoutが移行境界）。embedding＋ANN（pgvector or 外部Vector DB、`memory_embeddings`別テーブルにmodel_version列）。memoriesの日付パーティショニング。raw_contentのオブジェクトストレージ退避（Hot: summary/構造化データはDB、Cold: 原文はS3） |
| **10億件想定** | user_id（tenant）シャーディング。大口ユーザーの専用シャード。Hot/Warm/Coldの自動階層化。アーカイブDB分離。この規模では「単一memoriesテーブル」前提を放棄することが前提 |

**移行トリガー指標（監視すべきもの）**: 検索p95 > 300ms / 最大ユーザー件数 > 5万 / jobs滞留 > 1,000件 or 待ち > 60秒 / DBサイズ > 100GB / バッファプールヒット率低下。ユーザー数ではなくこれらの実測で判断する。

## 7. 未確認事項

- 本番MySQLの実バージョン・インスタンスサイズ・接続数上限（.envはsqlite。MySQL前提はmigrationコメントのみ）。
- MySQLでの実EXPLAIN（本監査のベンチはSQLite。LIKE走査の線形性は同じだが、filesort/temporary tableの正確な挙動はMySQLで再確認が必要）。
- worker構成（Supervisor/Horizon等）の有無 — F-1が示す通り現状は実質未使用。
- バックアップ・DR構成。connectorsテーブル経由の外部取り込み流量。
- これらは**未確認であり「問題なし」とは判定しない**。

## 最後の質問への回答

1. **毎回大量走査するか**: する。キーワード検索とRecallは対象ユーザーの全行（raw_content含む）をLIKE走査する。総件数ではなく**そのユーザーの件数**に線形。
2. **何件からp95悪化か**: LIKE検索の線形劣化は実測で確定（SQLite・1ユーザー1万件で40ms）。線形外挿では1ユーザー約10万件でp95 500msを割る公算だが、**確定値は本番相当MySQLでの再計測が必要**（v1.1訂正）。一覧・ステータス確認はインデックスが効き実測0.9ms/0.03ms @100万件 — ただし数億件ではインデックスサイズ・キャッシュ・バックアップという別制約が出る。
3. **数億件で詰まる箇所**: ①LIKE検索/Recall ②database queue単一worker ③ai_usage_logsの毎回SUM ④一覧の101件目以降到達不能。
4. **本当に使われるインデックス**: `(user_id,captured_at)`（一覧・検索の絞り込み）、`(user_id,status)`（ステータス）、`(user_id,created_at)`（利用額集計）。`user_id`単独は冗長、`(user_id,memory_type)`は半分だけ有効。
5. **数億件で同じDB構成を続けられるか**: 検索・Recall・ログ集計は**続けられない**。一覧・保存・ステータスもクエリ単体は速いままだが、数億件ではインデックス容量・書き込み増幅・バックアップ/リストア時間の制約が別に発生するため「そのまま続けられる」とは言えない（v1.1訂正）。
6. **検索基盤の分離**: 1ユーザー10万件級 or 検索p95>300msが常態化した時点で必要。FULLTEXT ngramで1,000万件級までは引き延ばせる。
7. **raw_contentをDBに持ち続けるべきか**: 〜1,000万件はDBで可（検索対象から外すことが条件）。1億件以降はS3等へ退避し、DBにはsummary＋検索用データのみ。
8. **分割開始の判断指標**: 上記「移行トリガー指標」参照。件数ではなくp95・滞留・最大ユーザー件数で判断。
9. **今すぐ vs 将来**: 今すぐ＝F-1・F-5（**PR1実装済み**）、F-2（PR2・予約制）、F-4（PR3）。将来＝FULLTEXT、ベクトル検索、パーティション、シャーディング。
10. **「将来に耐えられる」と言い切れるか**: 言い切れない。ただし「user_idスコープ強制」「上限付き取得」「IDのみのJob payload」「検索がService1クラスに隔離」という**移行境界は既にコードに存在する**。F-1/F-2修正後は「現規模＋計画的移行で耐えられる」と言える状態になる。

---

# 第2部: 事業・料金モデル設計

## 1. 結論

**推奨モデル: 月額サブスク＋AI利用枠（クレジット制）＋広いストレージ枠＋古い原文の自動アーカイブ**

| 項目 | 内容 |
|---|---|
| 対象顧客 | 最初は「記録習慣のある個人ナレッジワーカー・開発者」（セカンドブレイン層） |
| 提供価値 | 「保存は無意識、想起はAI」— 記録が増えるほど秘書が賢くなる |
| 基本料金 | Personal ¥980〜1,480/月、Pro ¥2,980〜3,980/月 |
| 課金単位 | 月額＋**AIクレジット**（AI整理=1クレジット、AI秘書チャット=実原価に応じ3〜10クレジット、長文は追加消費。**整理とチャットを同価値として扱わない**） |
| 無料枠 | 保存無制限に近く（例1,000件）、AIクレジットは少量。具体枠は**利用ログ2〜4週間の実測後に確定** |
| 上限超過 | 保存は止めない。**AI整理を保留キューに積み、翌月 or 追加パックで処理** |
| 主な利益源 | 月額サブスク（AI原価に対し粗利70%以上を設計） |
| 主な原価 | 初期はAIトークンが支配的。ストレージ系運用原価（インデックス・レプリカ・バックアップ・IOPS）は「誤差」ではなく内部監視対象（後述） |

**核となる原価事実（監査から導出、v1.1修正）**: 1記憶の本文データ単体は約1〜3KBで安価。一方AI整理は1件あたり2回のAPI呼び出しで、**初期段階の変動原価はAI呼び出し回数に支配される**。したがって「保存件数課金」は原価と乖離しており不適。ユーザーの仮説（保存は広く許可し、AI処理で原価管理）は原価構造と一致しており正しい。
ただし「保存原価は無視できる」とまでは言えない: 実運用原価には memories本体のほか、複数インデックス・memory_links・ai_usage_logs・recall_logs・バックアップ・レプリカ・binlog・検索インデックス・将来のベクトル・DB Compute・IOPS・障害復旧が含まれる。ユーザーに件数を意識させない方針は維持しつつ、**内部ではDB容量・インデックス容量・最大ユーザー件数・検索p95・バックアップ時間を監視**する。

## 2. ビジネスモデル候補比較

| モデル | メリット | デメリット | 粗利安定性 | UX | 実装難易度 | 推奨度 |
|---|---|---|---|---|---|---|
| ①完全月額 | 最も分かりやすい | ヘビーAI利用者で赤字（月3,000件整理＝原価$3-12＋chat次第で$15+） | × | ◎ | 低 | △ |
| ②月額＋純従量 | 原価完全連動 | 料金予測不能→記録をためらう＝プロダクト価値と矛盾 | ◎ | × | 中 | × |
| ③月額＋含有枠＋超過パック | 原価上限が明確、心理的安全 | 枠の説明が必要 | ○ | ○ | 中 | **◎ 推奨** |
| ④BYOK | AI原価ゼロ化 | サポート複雑、キー管理リスク | ◎ | △上級者のみ | 低 | ○ Proのオプション |
| ⑤アーカイブ階層 | 長期ストレージ原価抑制 | 現時点で原価が小さく効果薄 | ○ | ○ | 中 | 1億件時代の施策 |

## 3. 推奨料金プラン

| 項目 | Free | Personal ¥980-1,480 | Pro ¥2,980-3,980 | Team ¥1,980/席 | Enterprise 個別 |
|---|---|---|---|---|---|
| 対象 | 体験 | 日常記録者 | ヘビー個人・開発者 | 5-30人チーム | 法人 |
| 保存 | 1,000件 | 実質無制限(5万件) | 無制限 | 無制限 | 無制限 |
| AIクレジット | 少量（仮50） | 仮500 | 仮3,000 | 席比例 | カスタム |
| クレジット消費 | 整理=1 / チャット=3〜10（実原価連動） | 同左 | 同左 | 同左 | 同左 |
| 超過時 | 整理保留 | 追加パック¥500/500件 | 追加パック | プール共有 | コミット契約 |
| 検索 | ○ | ○ | ○＋意味検索(将来) | ○ | 専用検索基盤 |
| アーカイブ | 90日で原文Cold | なし(全Hot) | なし | なし | ポリシー設定可 |
| BYOK | × | × | ○(月額▲¥1,000) | × | ○ |
| エクスポート | ○（全プラン、常時、無条件 — データ人質化の禁止） | ○ | ○ | ○ | ○＋削除証明 |
| メンバー | 1 | 1 | 1 | 2-30 | 無制限+SSO+監査ログ |

**枠の数値はすべて仮値（v1.1）**: 旧案の「¥980で整理500件＋チャット300回」は枠上限利用時にAI原価だけで約$7.10≈¥1,080となり**販売価格を超えて赤字**（Laravel Cloud・DB・決済手数料・サポート・税は未算入）。¥1,480でも粗利は薄い。このため整理とチャットを同価値にせず、クレジット重み付け（チャット3〜10）で原価連動させる。確定枠は実利用ログを2〜4週間集めてから決める。
価格の前提となるモデル単価も固定しない: 現行公式ではHaiku 4.5が入力$1・出力$5/1Mトークン、Sonnet 5は2026-08-31まで入力$2・出力$10、以降$3・$15。さらにSonnet 5は同一文章でトークン数が約30%増える場合がある（公式案内）。原価式は単価変数（C_ai_*）のまま維持し、モデル更新のたびに再計算する。

Freeの長期コスト対策: 12ヶ月非アクティブでCold化（削除はしない・エクスポート常時可）。「勝手に削除しない」を守りつつ原価を抑える。

## 4. ユニットエコノミクス

変数定義（config/ai.php実値: haiku $1/$5、sonnet $3/$15 per 1M tokens）:
- `C_enrich` ≈ classify(600in/150out haiku) + extract(900in/400out haiku) ≈ **$0.004/件**（sonnet昇格時 $0.012）
- `C_chat` ≈ 3,000in/500out sonnet ≈ **$0.017/回**（history無制限問題F-10を直さないと増大）
- `C_recall(DB)` ≈ 0（現状AIを使わない。DB負荷のみ）
- `C_storage` ≈ $0.25/GB月 → 1万件≈20MB≈**無視できる**

| ユーザー像 | 月間利用 | 月間変動費 | 適プラン | 売上 | 粗利率 |
|---|---|---|---|---|---|
| 軽量（1-3件/日, chat10回） | 整理60件+chat10 | $0.4 (~¥60) | Personal ¥980 | ¥980 | **94%** |
| 標準（10件/日, chat100回） | 整理300件+chat100 | $2.9 (~¥440) | Personal | ¥980-1,480 | **55-70%** |
| ヘビー（100件/日, chat1,000回） | 整理3,000件+chat1,000 | $29 (~¥4,400) | Pro必須 | ¥3,980+パック | **薄利〜赤字**→枠で制御 |
| 長期5年・数十万件 | ストレージ0.5GB+検索負荷 | ¥数十＋検索基盤按分 | Pro | — | ストレージでは赤字にならない |

**赤字になる条件（v1.1で定量化）**: chat回数×sonnetが支配項。
- 旧Personal案: 整理500×$0.004＋チャット300×$0.017 = **$7.10 ≈ ¥1,080 > ¥980** → 枠上限利用でAI原価だけで販売価格を超過し赤字（インフラ・決済手数料・サポート・税は未算入）。
- 旧Pro案: チャット2,000回だけで≈$34≈¥5,100とPro上限価格を超え得る。
- 対策: chatの含有枠はRecall(DB)と分け、クレジット重み（3〜10）または実トークンベースで管理する。整理・保存単体では実質赤字にならない。

## 5. 赤字リスク（防ぐべき行動パターン）

1. **長文貼り付け大量整理**: 1件10万字の原文をextractに全文渡す（現実装は全文渡し）→ 入力トークン上限（例8,000字で切ってAIへ）をコード側に。
2. **無限chatセッション**: history無制限（F-10）→ 直近N往復＋要約に圧縮。
3. **APIコネクタ経由の自動大量投入**: connectorsからの流入にユーザー別レート制限（F-5対策と共通）。
4. Free滞留ユーザーのストレージ→Cold化で対応（原価的には軽微）。

## 6. 技術規模と料金モデルの対応

| 技術変化 | 発生規模（監査より） | 原価影響 | 料金への反映 |
|---|---|---|---|
| FULLTEXT ngram導入 | 1ユーザー1〜10万件 / 検索p95>300ms | ほぼゼロ（同一DB） | なし（品質向上として吸収） |
| 検索専用基盤 | 総数千万件 or 大口10万件超 | +$50-200/月固定 | Pro価格の根拠に |
| embedding＋ベクトル検索 | 意味検索リリース時 | +$0.0001/件(embedding)＋基盤費 | Pro限定機能として回収 |
| Read Replica | 読み取りCPU>60%常態 | DB費2倍 | ユーザー数増で吸収 |
| raw_content S3退避 | 総1億件 / DB100GB | DB費↓・S3費↑（純減） | アーカイブ枠の原資 |
| シャーディング/専用環境 | 大口テナント | 専有費 | Enterprise専用環境として販売 |

## 7. MVPで実装する課金機能（今すぐ）

監査のF-2修正と完全に同一の作業なので、**性能修正と課金基盤を一度に作る**:

1. `ai_usage_monthly`（user×月×featureのカウンタ、アトミック更新）← F-2の修正そのもの
2. プラン列（users.plan）と枠のconfig定義
3. 上限接近通知（80%でトースト）と使用量表示画面（カウンタテーブルを読むだけ）
4. 上限到達時は**保存は継続・AI整理のみ保留**（status=captured滞留→枠回復後に再処理。突然の機能停止を回避）
5. recall_logs（どの記憶を注入したか）＋回答末尾 `[[USED: ...]]` の記録 ← Lv3計測フック。referenced_count++の置換（F-4）と同一作業

## 8. 今は実装しないもの

Stripe連携の従量メータリング / チーム・Workspace / BYOK / ベクトル検索 / Cold storage / シャーディング / SSO・監査ログ / 追加パック購入フロー（枠管理だけ先に作り、購入は手動対応で検証）。

## 9. 検証計画（価格決定のための計測と質問）

計測（recall_logs＋ai_usage_monthlyで自動取得）: 保存件数/日の分布、AI整理の実原価/人/月、chat回数分布、Recall注入記憶の参照率（Lv3スコア）、90日継続率。
質問（β利用者へ）: 「月額いくらなら払うか」ではなく「**過去の記憶がAIの回答に出てきた瞬間があったか。それは何か**」（価値の実在確認）、「記録をためらった瞬間はあったか」（枠設計の検証）、「エクスポートできることは意思決定に影響したか」（信頼の価格転嫁可否）。

## 10. 最終判定

1. **B2C/B2B**: B2C個人（開発者・ナレッジワーカー）から。キオクの価値は個人の長期データ蓄積で生まれ、チーム価値はその後に派生する。
2. **月額のみで成立するか**: 軽量〜標準ユーザーには成立（粗利55-94%）。ヘビーには成立しない → 枠が必須。
3. **従量の使い所**: AIチャット/Recall（sonnet呼び出し）にのみ。保存・検索には使わない。
4. **保存件数課金**: すべきでない。原価と乖離し、記録抑制はプロダクト価値の自殺。
5. **AI利用量の見せ方**: 「トークン」ではなく「AI整理◯件/◯件、会話◯回/◯回」のプログレスバー。原文換算の生々しい数字は出さない。
6. **BYOK**: Pro限定オプションで提供（原価ヘッジ＋上級者の信頼獲得）。Freeには出さない。
7. **無料プラン**: 設ける。ただしAI枠を絞る（保存で絞らない）。
8. **長期保存データの収益化**: 「記憶が資産になる」こと自体が解約抑止＝LTVの源泉。原価はCold化で抑え、課金対象にはしない（人質化の禁止と両立）。
9. **数億件で粗利維持できるか**: できる — ただし第1部の移行計画（検索分離・原文退避）を実行した場合に限る。AI原価は件数でなく利用回数連動なので、枠モデルが維持されれば粗利構造は規模に対して不変。
10. **最初の有料顧客への提示価格**: **月¥980をβ仮価格**として提示するが、枠は約束しない（「β期間中は十分に使える」程度に留める）。2〜4週間の実利用ログでクレジット消費分布を実測してから、Personal正式枠とPro価格を確定する（v1.1訂正: 旧案の「¥980で整理500＋会話300」は原価逆転のため撤回）。

---

## 付記: プライバシーを競争優位に

キオクは人生・健康・感情のデータを扱う。以下を**料金表と同じページに明記**することが、Notion/Evernote等との最大の差別化になる:
広告販売しない / 当社独自のAI学習には利用しない / 全プラン無条件エクスポート / 完全削除（バックアップからの削除期限含む）/ 退会後90日の猶予 / BYOK時の責任分界。

**表現の正確性（v1.1訂正）**: 「Anthropicのゼロデータ保持」は通常API契約では**言えない**。Anthropic公式では通常のAPI入出力は原則として受領・生成から30日以内に削除され、Zero Data Retentionは別途合意した場合の例外扱い。表示できるのは「AI処理のためAnthropic APIへ送信します。Anthropic側では標準で最大30日保持される場合があります」まで。

**sensitiveフラグの実態（v1.1訂正）**: 現実装はRecall（ヨユウ等への注入）から除外するだけで、**保存直後のAI整理（EnrichMemoryJob）では原文がAnthropicへ送信される**。したがって宣伝できるのは「センシティブ指定された記憶はRecallには使用されない」まで。「AIに一切渡らない」を実現するには、保存前のsensitive指定 → EnrichMemoryJobを実行しない → 原文のみ暗号化保存、という別仕様が必要（将来検討）。
