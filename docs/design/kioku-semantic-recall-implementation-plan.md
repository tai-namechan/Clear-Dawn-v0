# キオク Capture拡張・意味想起 実装計画書

- 状態: 実装投入可能
- 作成日: 2026-07-29
- 対象: Cursor / Codex / Fable等の実装Agent
- 依存文書:
  - `kioku-canonical-capture-pipeline-v2.md`
  - `kioku-semantic-recall-expansion-spec.md`

---

## 0. 実装方針

一度に巨大PRへしない。依存順に分割し、各PRを単独でrollback・評価できるようにする。

推奨順:

```text
PR-A  Capture境界共通化（挙動不変）
  ↓
PR-B  音声ファイル取込
  ↓
PR-C  Embedding基盤
  ↓
PR-D  Hybrid Search＋想起Feedback
  ↓
PR-E  iOS Shortcut
  ↓
PR-F  Obsidian出力＋行動化
```

最優先価値は PR-B〜D。PR-E/Fは価値確認後でもよい。

---

## 1. 実装前監査

Agentは報告を信用せず、現在のrepositoryで確認する。

### 1.1 必須確認

- `CLAUDE.md` / `AGENTS.md`
- docsの正本ルール
- 現在branch / HEAD / status
- PR #131等がmerge済みか
- Capture Controller / Service / Requests
- IndexedDB queue
- `client_capture_id`
- `memory_assets`
- voice retry / MIME allow-list
- `TranscriptionGateway`
- `EnrichMemoryJob`
- AI usage ledger
- tag search / Context Builder
- Recallの参照回数副作用
- sensitive除外
- キオク便りの候補選定
- MySQL本番 / SQLite testの差

### 1.2 停止条件

- worktreeに未確認のユーザー差分がある
- docsと実装のどちらが正か判断できない
- raw不変条件の現在実装が不明
- current main/developの関係が不明
-既存の同等interface / table / jobが存在する

重複実装せず、差分を報告して判断する。

---

## 2. PR-A — Capture境界共通化

### 2.1 目的

既存のブラウザ録音・テキスト・URLの挙動を変えず、今後の入口を差し込める境界を作る。

### 2.2 docs先行

- pipeline v2をrepository docsへ配置
- data table更新
- source_type互換方針
- raw_kind / capture_channelの判断

### 2.3 実装

- `RawKind` enum
- `CaptureChannel` value object / enum
- `CapturedRaw` DTO群
- `CaptureAdapter` interface
- `CanonicalRawStore` interfaceまたは既存Capture Serviceへの同等境界
- `MemoryProcessingPipeline`
- 既存text/browser voice/urlをAdapter経由へ接続

実際のクラス名はrepositoryの命名規則へ合わせる。

### 2.4 Migration

同等情報がなければ:

- `memories.raw_kind` nullable
- `memories.capture_channel` nullable
- index `(user_id, raw_kind, status)`
- backfill command

既存migrationを書き換えずforward migrationを追加。

### 2.5 テスト

- 既存capture回帰
- backfill dry-run
- source_type fallback
- duplicate client_capture_id
- raw不変
- audio Asset不変
- owner境界

### 2.6 完了条件

UI挙動と既存Endpointのresponseが変わらず、内部境界だけが共通化される。

---

## 3. PR-B — 音声ファイル取込

### 3.1 Backend

- multipart endpoint
- server-side MIME
- 24MB limit
- `video/mp4` audio container対応
- private Asset
- transaction + orphan cleanup
- idempotency
- 既存Transcription Jobへdispatch

### 3.2 Frontend

- 「音声ファイルを取り込む」
- file picker
- client validation
- progress
- success / error
- raw保存とAI処理の表示分離
- mobile操作

### 3.3 Tests

最低限:

- mp3
- m4a / mp4
- wav
- webm
- unsupported
- 24MB境界
- duplicate
- DB failure cleanup
- other user 404
- transcription provider none/openai
- imported voiceがDetailで再生可能

### 3.4 品質ゲート

- Chrome
- iPhone Safari
- 再deploy後再生
- 文字起こし→Enrich
- raw消失0

---

## 4. PR-C — Embedding基盤

### 4.1 Backend

- `SearchDocumentBuilder`
- `EmbeddingGateway`
- OpenAI adapter
- `VectorStore`
- MySQL JSON MVP adapter
- `memory_embeddings`
- `GenerateMemoryEmbeddingJob`
- usage ledger統合
- status command
- backfill / rebuild command

### 4.2 Commands

```bash
php artisan kioku:embeddings:status

php artisan kioku:embeddings:backfill \
  --user=<USER_ID> \
  --dry-run \
  --limit=100

php artisan kioku:embeddings:backfill \
  --user=<USER_ID> \
  --limit=100

php artisan kioku:embeddings:rebuild \
  --user=<USER_ID> \
  --model=text-embedding-3-small \
  --dry-run
```

実装時に既存command命名へ合わせる。

### 4.3 重要テスト

- Search Document順序
- content hash
- hash同一でAPI 0
- field変更でAPI 1
- sensitiveでAPI 0
- sensitive化でvector削除
- other user非混入
- stale Job拒否
- transient / permanent
- actual usage
- rawログ非出力
- backfill user限定

### 4.4 Feature Flag

初回merge時:

```env
KIOKU_EMBEDDING_ENABLED=false
KIOKU_SEMANTIC_SEARCH_ENABLED=false
```

本番migration後、自分のuser限定backfillを行ってからONにする。

---

## 5. PR-D — Hybrid Search＋想起Feedback

### 5.1 Backend

- vector query embedding
- tag/fulltext/vector/link candidate pools
- RRF
- reason生成
- top4 recall response
- feedback保存
- lightweight personal rerank
- existing Context Builderへoptional semantic candidates

既存Recallの `referenced_count` / `last_referenced_at` 等の副作用を壊さない。

### 5.2 Frontend

検索画面にmodeを追加:

- 絞り込み検索
- 「あれなんだっけ」

結果カード:

- reason
- `これこれ！`
- `少し関係ある`
- `違う`

### 5.3 Eval

本番Memoryから答えが分かる20問を事前登録する。

比較:

1. fulltext only
2. tag + fulltext
3. tag + fulltext + vector
4. hybrid + feedback rerank

Top4 HIT率とP95を記録する。

### 5.4 Kill条件

- owner漏洩
- sensitive漏洩
- tag+fulltextよりHIT率悪化
- 1,000件未満でP95 500ms超

---

## 6. PR-E — iOS Shortcut

### 6.1 Backend

- scoped capture token
- token hash
- revoke
- rate limit
- idempotency
- unified endpoint
- text/url/audio adapter

### 6.2 Deliverables

- Shortcut手順書
- sample Shortcut
- token再発行手順
- loss/retry確認

### 6.3 Security

- tokenをURL queryへ入れない
- app logsへ出さない
- capture以外のscopeを与えない
- tokenごとにlast_used_at

---

## 7. PR-F — Obsidian出力・行動化

### 7.1 Obsidian

- ZIP export
- Markdown + frontmatter
- optional audio
- export log
- sensitive default除外

### 7.2 Clear Dawn / ヨユウ

- explicit user action
- preview
- provenance Memory ID
- duplicate prevention

自動作成は別実験まで行わない。

---

## 8. 環境変数

### 8.1 新規候補

```env
# Audio import
KIOKU_AUDIO_IMPORT_ENABLED=false
KIOKU_AUDIO_IMPORT_MAX_MB=24

# Embedding
KIOKU_EMBEDDING_ENABLED=false
KIOKU_EMBEDDING_PROVIDER=openai
KIOKU_EMBEDDING_MODEL=text-embedding-3-small
KIOKU_EMBEDDING_BATCH_SIZE=50
KIOKU_EMBEDDING_MAX_MEMORIES_PER_USER=1000

# Search
KIOKU_SEMANTIC_SEARCH_ENABLED=false
KIOKU_RECALL_FEEDBACK_ENABLED=false

# iOS
KIOKU_IOS_CAPTURE_ENABLED=false

# Export
KIOKU_OBSIDIAN_EXPORT_ENABLED=false
```

### 8.2 既存OpenAI key

既存keyを使う場合、Restricted permissionsへEmbeddings writeを追加する。

分離する場合:

```env
OPENAI_EMBEDDING_API_KEY=
```

未設定時に既存 `OPENAI_API_KEY` へfallbackする設計は可能だが、fallbackの有無をdocsへ明記する。

### 8.3 維持

```env
KIOKU_AUDIO_DISK=kioku-audio
KIOKU_TRANSCRIPTION_PROVIDER=openai
OPENAI_API_KEY=...
ANTHROPIC_API_KEY=...
```

---

## 9. Deploy手順

各PR:

1. docs確認
2. migration backup方針
3. deploy
4. `migrate --force`
5. config確認
6. queue / scheduler確認
7. feature flag OFFでsmoke
8. 自分のuser限定dry-run
9. 自分のuser限定実行
10. UI確認
11. flagを段階的にON

Embedding rollout:

```text
Gateway ON
  ↓
自分のMemory 100件backfill
  ↓
検索eval
  ↓
semantic search ON
  ↓
友人beta
```

---

## 10. 共通品質ゲート

repositoryに存在する正規コマンドを使う。

- PHP全テスト
- Kioku Feature / Unit
- JS tests
- TypeScript
- production build
- asset budget
- formatter
- PHPStan今回差分
- ESLint今回差分
- `git diff --check`

追加:

- 実外部HTTPは専用staging smoke以外0
- logsにraw/transcript/vector/keyがない
- queue retryで重複課金しない
- owner/sensitive SQL除外
- browser/iPhone実機

---

## 11. 実装Agentへの完全プロンプト

以下をそのまま実装Agentへ渡せる。

```text
あなたはClear-Dawn-v0のシニアLaravel/Vueエンジニアです。
以下2文書を仕様の入力として、キオクのCapture拡張と意味想起を実装してください。

- kioku-canonical-capture-pipeline-v2.md
- kioku-semantic-recall-expansion-spec.md

最初に必ずrepositoryを監査し、報告内容を信用せず実コードを確認してください。
CLAUDE.md / AGENTS.md / docs正本ルールを最優先してください。

重要:
- 既存QC-1〜QC-3、OpenAI文字起こし、Anthropic整理、IndexedDB、
  client_capture_id、AI usage ledger、タグ検索、Context Builder、
  キオク便りを作り直さない
- raw_contentと原音声を変更しない
- source_type互換を壊さない
- sensitiveと他ユーザーをSQL段階で除外する
- 新しい入力元だけをAdapter化し、保存後のJobを共通化する
- 音声ファイルそのものをEmbeddingへ送らない
- text-embedding model名・料金・次元をハードコードせずconfig化する
- Embedding失敗時もタグ＋全文検索を継続する
- 一つの巨大interface / 巨大Job / 巨大PRにしない
- 既存migrationを書き換えずforward migrationを使う

実装順:
1. 現状監査と差分表
2. docs先行更新
3. PR-A相当: Capture境界共通化（挙動不変）
4. PR-B相当: 24MB音声ファイル取込
5. PR-C相当: Search Document / EmbeddingGateway / VectorStore /
   memory_embeddings / Job / backfill
6. PR-D相当: タグ＋全文＋ベクトルのHybrid Search /
   「あれなんだっけ」Top4 / HIT・RELATED・MISS

iOS ShortcutとObsidian出力は、上記がgreenになった後の独立差分とし、
同一PRへ混ぜないでください。

MVP VectorStore:
- 本番DBがMySQLでnative ANNが未確認なら推測で依存しない
- interfaceを切り、初期はMySQL JSON保存＋PHP cosine
- user 1,000件 hard cap
- P95 300msを移行判断基準

Audio import:
- 24MB
- server-side MIME
- mp3/mp4/mpeg/mpga/m4a/wav/webm
- video/mp4 audio containerを拒否しない
- private Asset
- transaction / orphan cleanup / idempotency
- 既存TranscribeMemoryAudioJobを再利用

Embedding input v1:
- title
- summary
- transcript_text
- tags
- memory_type
- insight
- next_action
- raw audio / asset URL / sensitive / kioku_letterは含めない

Search:
- explicit tagsはAND/OR filter
- vector/fulltext/tag/link候補を取得
- RRFで統合
- reasonを返す
- provider停止時はtag+fulltext fallback

作業前に branch / HEAD / status / diff / open PRを確認してください。
ユーザーの既存差分を消さないでください。
reset --hard / force push / rebase / amendは禁止です。

各段階でテストを追加し、最後にrepository標準の全品質ゲートを実行してください。
実外部HTTPはテストから呼ばないでください。

完了報告:
1. 総合判定
2. 実装前の現状
3. 再利用した既存機構
4. docs差分
5. migration
6. interfaceと状態遷移
7. audio import
8. Embedding / cost / retry
9. Hybrid ranking
10. security / privacy
11. testsと全コマンド結果
12. 未実施の実機・本番作業
13. scope外差分
14. git status

最初の実装セッションではcommit / push / PR / merge / deployを行わず、
全検証後に差分とリスクを報告して停止してください。
```

---

## 12. 最終判断

### 今すぐ実装

1. Capture境界共通化
2. 音声ファイル取込
3. Embedding基盤
4. Hybrid Search
5. 想起Feedback

### 価値確認後

6. iOS Shortcut
7. Obsidian export
8. Clear Dawn / ヨユウ行動化

理由:

- 入口拡張だけでは「思い出せる価値」が増えない
- EmbeddingだけではUXにならない
- Hybrid SearchとFeedbackまで揃って初めて、はやとの言うパターン補完を検証できる
- iOS/Obsidianはその価値が確認できてから増やしても遅くない
