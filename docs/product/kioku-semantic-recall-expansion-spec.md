# キオク 意味想起・入力拡張 完全仕様

- 状態: 実装前確定案
- 作成日: 2026-07-29
- 文書種別: **新規機能仕様**
- 依存: `kioku-canonical-capture-pipeline-v2.md`

---

## 0. 目的

キオクを「保存できるメモ」から、次の状態へ進める。

> 曖昧な手がかりから、過去の自分の経験を見つけ直せる記憶基盤。

追加する能力:

1. 音声ファイルを取り込める
2. 整理済みMemoryをEmbedding化できる
3. タグ・全文・ベクトルを統合して検索できる
4. 「あれなんだっけ」に3〜4件の候補を返せる
5. HIT/MISSの反応を次の検索と手紙の評価データへできる
6. iOS Shortcutから同じ記憶基盤へ送れる
7. Obsidianへ持ち出せる
8. Clear Dawn / ヨユウへ明示操作で行動化できる

---

## 1. タグとEmbeddingの役割

タグとEmbeddingは同じものではなく、相互補完である。

| 仕組み | 役割 | 強み | 弱み |
|---|---|---|---|
| タグ | 人間が理解できる明示軸 | 編集・説明・フィルタ・個人化 | 表記揺れ、付け忘れ、離散的 |
| 全文検索 | 言葉が一致する記憶 | 固有名詞・エラー文・URL | 言い換えに弱い |
| Embedding | 意味的な近さ | 曖昧な言い換え・関連概念 | 理由が人間に見えにくい |
| Feedback | 個人にとっての有用性 | 自分固有の再ランキング | データが貯まるまで弱い |

設計原則:

> タグをベクトルの代用品にせず、Embeddingをタグの代用品にしない。

---

## 2. 全体構造

```text
各Capture Adapter
        │
        ▼
Canonical Raw
        │
        ▼
Normalized Content
        │
        ▼
AI整理・タグ
        │
        ▼
Search Document
        │
        ▼
Embedding Gateway
        │
        ▼
Vector Store
        │
   ┌────┴─────────────┐
   ▼                  ▼
Hybrid Search      Context Builder
   │                  │
   ▼                  ▼
曖昧想起UI          キオク便り
   │
   ▼
HIT / RELATED / MISS
   │
   ▼
Personal Reranking Data
```

---

## 3. Feature A — 音声ファイル取込

### 3.1 ユーザーストーリー

小型レコーダーやiPhoneのボイスメモで録音したファイルを、キオクへ取り込みたい。取り込んだ後はブラウザ録音と同じように文字起こし・AI整理・検索が行われてほしい。

### 3.2 入力

- 1回1ファイル
- 初期上限: **24MB**
- 許可候補: mp3 / mp4 / mpeg / mpga / m4a / wav / webm
- server-side MIME検出を正とする
- `audio/*` に加え、音声を含む `video/mp4` containerを許可対象として扱う

OpenAIの現行Speech-to-Text APIはファイル入力上限が25MBのため、アプリ側はヘッダ等の余裕を持って24MBとする。上限・形式はprovider変更に備えconfig化する。

### 3.3 UI

Capture cardに追加:

```text
[音声で残す] [音声ファイルを取り込む] [保存する]
```

取込中:

- ファイル名
- サイズ
- upload進捗
- キャンセル
- 「原音声を保存中」

完了後:

- 「原音声を保存しました。文字起こしはあとから行います」

### 3.4 保存

- `raw_kind=audio`
- `capture_channel=audio_file_import`
- private `audio_original` Asset
- `client_capture_id` 必須
- Server ACK前に保存済み表示を出さない
- upload失敗時にMemoryだけを残さない
- DB失敗時に孤児Assetを残さない

### 3.5 後段

既存の次を再利用する。

- `TranscriptionGateway`
- `TranscribeMemoryAudioJob`
- AI usage ledger
- retry command
- `EnrichMemoryJob`
- 検索・手紙

ファイル取込専用の文字起こしJobを作らない。

---

## 4. Feature B — Search Document

Embeddingへ渡す文字列を一箇所で確定し、どのJobからも同じ順序で生成する。

### 4.1 v1構成

```text
title: {title}
summary: {summary}
transcript: {transcript_text}
tags: {comma-separated tags}
type: {memory_type}
insight: {structured_data.insight}
next_action: {structured_data.next_action}
```

対象フィールド:

- title
- summary
- transcript_text
- tags
- memory_type
- insight
- next_action

### 4.2 入れないもの

- 音声バイナリ
- Asset URL
- API key
- user名
- sensitive Memory
- キオク便りの評価Memory
- 失敗ログ

`raw_content` 全文はv1では直接含めない。精度評価で固有情報の欠落が多い場合に限り、長さ制限付き `normalized_excerpt` をv2候補とする。

### 4.3 正規化

- trim
- 改行統一
- タグ順序を正規化
- nullは空欄
- 同じ見出し順
- 最大文字数またはtoken上限

### 4.4 Content Hash

Search Documentを正規化した後、hashを作る。

```text
content_hash = sha256(
  schema_version + model + normalized_search_document
)
```

hashが同じなら再Embeddingしない。

---

## 5. Feature C — Embedding基盤

### 5.1 Provider

初期推奨:

- provider: OpenAI
- model: `text-embedding-3-small`
- endpoint: `/v1/embeddings`

2026-07-29時点の公式モデル情報では、同モデルはテキスト専用で、検索・クラスタリング等に利用でき、入力料金は $0.02 / 1M tokens。価格は運用時に再確認し、コードへ固定せずconfigと料金台帳へ置く。

### 5.2 Gateway

```php
interface EmbeddingGateway
{
    public function embed(EmbeddingRequest $request): EmbeddingResult;
}
```

Result:

- vector
- model
- dimensions
- input_tokens
- request_id
- actual_usd

音声文字起こしのGatewayと混ぜない。

### 5.3 Vector Store

```php
interface VectorStore
{
    public function upsert(MemoryEmbedding $embedding): void;
    public function deleteForMemory(string $memoryId): void;
    public function nearest(VectorQuery $query): array;
}
```

MVP:

- MySQL JSON / longTextにvectorを保存
- PHP側でcosine similarity
- user単位のhard cap: 1,000 current embeddings
- topK: 40以下

これは友人ベータ向けの最小構成である。次のどちらかを超えたらANN対応Storeへ移行する。

- 1ユーザー1,000件超
- 意味検索P95が300ms超

Store interfaceにより、将来Qdrant・pgvector等へ差し替え可能にする。

### 5.4 memory_embeddings

推奨スキーマ:

| カラム | 用途 |
|---|---|
| `id` | ULID |
| `user_id` | owner境界と検索効率 |
| `memory_id` | Memory |
| `provider` | provider |
| `model` | model |
| `dimensions` | vector次元 |
| `schema_version` | Search Document版 |
| `content_hash` | 再生成判定 |
| `vector` | JSON / longText |
| `status` | pending / processing / ready / failed |
| `input_tokens` | 利用量 |
| `actual_usd` | 課金 |
| `embedded_at` | 完了時刻 |
| `error_code` | 内容を含まない分類 |

制約:

- current相当の `(memory_id, provider, model, schema_version)` は一意
- ownerをMemory任せにせず `user_id` でも絞る
- Memory削除でcascade

### 5.5 Job

`GenerateMemoryEmbeddingJob`

- Enrich成功後にdispatch
- ShouldBeUnique
- 条件付きclaim
- `content_hash` 同一ならAPI 0回
- transientのみretry
- rawを変更しない
- sensitiveならAPI 0回・vector削除
- 古いJobのstale writeを拒否

### 5.6 再生成

次で再Embeddingする。

- title / summary / tags / type / insight / next_action変更
- transcript再生成
- model変更
- schema_version変更
- sensitive解除後の明示操作

---

## 6. Feature D — Hybrid Search

### 6.1 検索入力

- 自由文 `q`
- tags
- tag mode AND / OR
- type
- source
- date range
- `semantic=true|false`

### 6.2 Candidate Pool

独立して候補を取る。

1. タグ候補
2. 全文候補
3. ベクトル候補
4. 明示リンク候補

各候補はowner / ready / non-sensitive / non-letterをSQL段階で満たす。

### 6.3 統合

初期実装はReciprocal Rank Fusionを推奨する。

```text
rrf = Σ source_weight / (60 + rank_in_source)
```

初期weight:

- tag exact: 1.4
- vector: 1.2
- full text: 1.0
- explicit link: 1.3

補正:

- importance: 小さな加点
- recent reference: 小さな加点
- repeated MISS: 減点
- repeated HIT: 加点

補正だけで順位を独占しないよう上限を持たせる。

### 6.4 明示タグの扱い

ユーザーがタグを選んだ場合、そのタグは原則filterである。

- AND: 全選択タグを含む
- OR: いずれかを含む

自由文からAIが推定したタグはfilterではなくboostにする。推定ミスで候補をゼロにしないためである。

### 6.5 Fallback

- Embedding provider停止時: タグ＋全文で継続
- Embedding未生成: 生成済みMemoryだけ意味候補、他は全文候補
- query空: 意味検索しない
- 結果0: フィルタ解除候補を提示するが勝手に変更しない

---

## 7. Feature E — 「あれなんだっけ」想起UI

### 7.1 目的

完全な検索語を思い出せない時に使う。

例:

> 前に、入力元を増やしても後段を共通にする話をした気がする

### 7.2 表示

最大4件。

各候補:

- title
- excerpt
- 日付
- tags
- なぜ出したか
- 元Memoryへのリンク

理由の例:

- 「“入力元”と“共通処理”の意味が近い」
- 「#システム設計 が一致」
- 「以前HITにした記憶と同じタグ帯」

### 7.3 Feedback

各候補へ:

- `これこれ！` = HIT
- `少し関係ある` = RELATED
- `違う` = MISS

検索セッション全体へ:

- `見つからなかった`

### 7.4 Feedback保存

`kioku_recall_feedback`:

- user_id
- search_session_id
- query_hash
- memory_id
- shown_rank
- tag_rank
- fulltext_rank
- vector_rank
- final_score
- verdict
- created_at

初期実装では生queryを保存しない。改善に必要ならユーザー同意の上で別途検討する。

### 7.5 活用

初期:

- 評価レポート
- 同じMemory・タグ帯の軽いrerank

将来:

- キオク便り候補品質
- 個人別ranker

Embeddingモデル自体をユーザーごとに学習し直さない。

---

## 8. Feature F — iOS Shortcut

### 8.1 スコープ

ネイティブアプリを作らず、共有シート／Shortcutから送る。

対応:

- text
- URL
- audio file

### 8.2 認証

- 個人ごとのrevocable token
- DBにはhashのみ
- scope: `kioku:capture`
- 作成時以外は平文表示しない
- rate limit
- 最終利用日時

Session cookieをShortcut API認証へ流用しない。

### 8.3 API

```text
POST /api/kioku/captures
Authorization: Bearer ...
Idempotency-Key: UUID
```

bodyはraw kindごとに検証する。保存後は共通Pipelineへ渡す。

### 8.4 Shortcut UX

1. 共有
2. 「キオクへ残す」
3. 送信中
4. 「原情報を保存しました」

AI整理完了を待たない。

---

## 9. Feature G — Obsidian出力

### 9.1 位置づけ

キオクがsource of truth。Obsidianは可搬性とローカル閲覧の出力先。

### 9.2 MVP

- ユーザー操作によるZIP export
- 1 Memory = 1 Markdown
- 添付音声は任意同梱
- YAML frontmatter

```yaml
---
kioku_id: 01...
created_at: 2026-07-29T...
type: idea
tags:
  - AI
  - 記憶
source: voice
---
```

本文:

- title
- summary
- insight
- next_action
- transcript（ユーザーが選択した場合）
- original link

### 9.3 非目標

- 双方向同期
- Obsidian側編集の自動取込
- ファイル監視
- conflict resolution

---

## 10. Feature H — Clear Dawn / ヨユウへ行動化

Memoryから次を明示操作で作る。

- Clear Dawnの相談文脈
- ヨユウのtask
- review reminder

AIが勝手にtaskを作らない。

作成時に表示:

- 何を作るか
- どのMemoryが根拠か
- 作成先

Memory IDをprovenanceとして保持する。

---

## 11. コスト設計

### 11.1 Embedding

2026-07-29時点の `text-embedding-3-small` 公式入力価格は $0.02 / 1M tokens。

概算例:

- 1,000 Memories
- 1件平均300 tokens
- 合計300,000 tokens
- 初回Embedding概算: $0.006

検索query:

- 1,000 queries
- 1件平均30 tokens
- 30,000 tokens
- 概算: $0.0006

実際の課金は公式価格とusageを台帳で計算する。

### 11.2 制御

- 月額ユーザー上限へ統合
- backfillは `--dry-run`, `--user`, `--limit`
- batch sizeをconfig化
- 同じcontent hashを再課金しない
- provider/model/schema別に追跡

### 11.3 API key

既存 `OPENAI_API_KEY` を再利用する場合、Restricted keyに `/v1/embeddings` のwrite権限が必要。

より厳密に分離する場合のみ `OPENAI_EMBEDDING_API_KEY` を追加し、未設定時は既存OpenAI keyへfallbackする。

---

## 12. 成功条件

### 12.1 音声取込

- 代表形式の取込成功率95%以上
- raw消失0
- 同じclient_capture_idの重複0
- browser録音との後段差分0

### 12.2 意味検索

事前登録した20問で:

- Top4に正解: 70%以上
- タグ＋全文のみ比でTop4 HIT率が10pt以上改善
- 検索P95: 300ms以下（1,000件以下）

### 12.3 想起UX

- `これこれ！` または `少し関係ある`: 40%以上
- MISSのみの検索が3回連続するユーザーが50%超ならrankingを見直す

### 12.4 継続

- Embedding失敗で既存検索が停止しない
- providerをOFFにしてもCapture・全文・タグ・手紙が継続

---

## 13. 中止・再設計条件

- sensitiveが1件でもvector検索へ漏れた
- 他ユーザーMemoryが1件でも候補に出た
- raw消失が再現した
- 1,000件未満でP95が500ms超
- 意味検索を加えてHIT率が改善しない
- 入力拡張で23時のCapture時間が30秒を超える

Critical条件発生時は新機能追加を止め、境界修正を優先する。

---

## 14. 非目標

- 音声ファイルそのもののEmbedding
- 画像・動画Embedding
- 個人ごとのニューラルネット再学習
- 自動タグだけによる物理フォルダ生成
- 全MemoryをLLMのcontextへ投入
- realtime常時録音
- Obsidian双方向同期
- 自動task生成

---

## 15. 公式情報

- OpenAI `text-embedding-3-small`: https://developers.openai.com/api/docs/models/text-embedding-3-small
- OpenAI `gpt-4o-mini-transcribe`: https://developers.openai.com/api/docs/models/gpt-4o-mini-transcribe
- OpenAI Speech to text guide: https://developers.openai.com/api/docs/guides/speech-to-text

価格・上限・利用可能modelは変更され得るため、実装時と本番有効化時に再確認する。
