# キオク Canonical Capture Pipeline v2 設計書

- 状態: 実装前確定案
- 作成日: 2026-07-29
- 対象: Clear Dawn / キオク
- 文書種別: **既存設計の修正版**

---

## 0. 結論

今回、既存設計を全面的に作り直す必要はない。

変更するのは次の4点である。

1. `source_type` に混在している「データの種類」と「入力経路」を分ける
2. rawを `raw_content` だけで表さず、媒体ごとの一次情報として定義する
3. ブラウザ録音・ファイル取込・iOS Shortcutを、保存後は同じ処理へ流す
4. AI整理の後段にEmbedding生成を追加できる共通パイプラインへする

既存の次の原則は変更しない。

- 23時でも短時間で残せる
- AIやネットワークを待ってからrawを保存しない
- テキスト原文と原音声は書き換えない
- 文字起こし・AI整理・Embeddingに失敗しても一次情報は残る
- sensitiveな記憶は表出・意味検索・手紙の対象にしない
- タグは人間が理解できる個人の軸として残す

中核原則は次の一文に集約する。

> 入力元は増えても、raw保存後の処理は一つにする。

---

## 1. なぜ修正が必要か

現在はブラウザ録音が成立している一方、今後は次の入力元が増える。

- 音声ファイル取込
- iOS Shortcut
- 小型レコーダーから書き出したファイル
- 将来の外部コネクタ

入力元ごとに「文字起こし→AI整理→タグ→検索→手紙」を実装すると、以下が重複する。

- retry
- 冪等性
- 課金管理
- status管理
- sensitive除外
- 検索インデックス更新
- テスト

したがって、入口だけをAdapterとして分け、Canonical Raw保存以降を共通化する。

---

## 2. 変更・追加・維持の分類

| 区分 | 対象 | 判断 |
|---|---|---|
| 修正 | rawの定義 | `raw_content`中心から媒体別Canonical Rawへ拡張 |
| 修正 | `source_type`の責務 | データ種別と入力経路を分離 |
| 修正 | 処理起動 | Controllerごとの分岐ではなく共通Pipelineへ |
| 修正 | 再整理後処理 | 検索用テキスト変更時はEmbeddingも更新 |
| 追加 | 音声ファイル取込 | 新しいCapture Adapterとして追加 |
| 追加 | iOS Shortcut | 新しいCapture Adapterとして追加 |
| 追加 | URL正規化 | URL本文抽出をNormalizerとして明示 |
| 追加 | Embedding | Enrich後の独立Stageとして追加 |
| 維持 | ブラウザ録音 | 既存UI・原音声保存・文字起こしを再利用 |
| 維持 | OpenAI文字起こし | Audio Normalizerの実装として再利用 |
| 維持 | Anthropic整理 | Enrich Stageとして再利用 |
| 維持 | IndexedDB | 消失し得るブラウザ録音・短文rawの保護に利用 |
| 維持 | キオク便り | Hybrid Searchの結果を将来利用できる下流機能 |

---

## 3. 用語

### 3.1 Raw Kind

何を一次情報として保存したか。

| raw_kind | Canonical Raw |
|---|---|
| `text` | `raw_content` |
| `audio` | `memory_assets.kind=audio_original` の非公開ファイル |
| `url` | 入力されたURL文字列と取得時刻。必要に応じて不変URL snapshot |

### 3.2 Capture Channel

どの入口から入ったか。

初期値:

- `web_text`
- `web_url`
- `browser_voice`
- `audio_file_import`
- `ios_shortcut`
- `system_connector`

将来値:

- `recorder_import`
- `slack_connector`
- `obsidian_import`

### 3.3 Canonical Raw

後からAIで再生成できない一次情報。作成後に破壊的更新しない。

### 3.4 Normalized Content

AI整理へ渡せるテキスト表現。

- text: 原文
- audio: 文字起こし
- url: URL本文抽出結果

Normalized Contentは派生情報であり、訂正・再生成・版管理が可能である。

### 3.5 Enriched Content

AIが生成する意味層。

- title
- summary
- tags
- memory_type
- insight
- next_action
- structured_data

---

## 4. 正式フロー

```text
テキスト・音声・URL・iOSショートカット
                  │
                  ▼
             即時raw保存
                  │
       ┌──────────┼──────────┐
       ▼          ▼          ▼
  raw_content   原音声      URL原情報
   不変保存     不変保存      保存
       │          │          │
       │          ▼          ▼
       │      文字起こし    URL本文抽出
       │          │          │
       └──────────┴──────────┘
                  │
                  ▼
           AI整理・タグ生成
                  │
                  ▼
     検索用テキストをEmbedding化
                  │
                  ▼
       タグ＋全文＋ベクトル検索
                  │
        ┌─────────┴─────────┐
        ▼                   ▼
     キオク便り          Obsidian出力
        │
        ▼
 Clear Dawn・ヨユウで行動へ
```

### 4.1 実装Stage

```text
Capture Adapter
    ↓
Canonical Raw Store
    ↓
Normalize Stage
    ↓
Enrich Stage
    ↓
Embedding Stage
    ↓
Search Index / Recall
```

Stageは一つの巨大Jobにしない。各Stageは独立して再試行でき、前段の成功物を再利用する。

---

## 5. データ責務

### 5.1 memories

既存カラムを維持した上で、同等フィールドが存在しなければ次を追加する。

| カラム | 型の例 | 用途 |
|---|---|---|
| `raw_kind` | varchar / enum相当 | `text` / `audio` / `url` |
| `capture_channel` | varchar | 入力経路 |
| `client_capture_id` | UUID、既存維持 | 冪等性 |
| `raw_content` | nullable text、既存維持 | textとurlのCanonical Raw |
| `transcript_text` | nullable longText、既存維持 | 音声の派生テキスト |
| `status` | 既存維持 | Memory全体の利用可能状態 |

`source_type` は即時削除しない。表示・既存集計・後方互換のため残し、段階的に `raw_kind` / `capture_channel` へ責務を移す。

### 5.2 memory_assets

| kind | 用途 | 不変性 |
|---|---|---|
| `audio_original` | 原音声 | 不変 |
| `url_snapshot` | 取得時点の本文またはHTMLを保存する場合 | 不変 |
| 将来の添付 | 画像等 | kindごとに定義 |

外部公開URLをDBへ保存しない。所有者認可付きEndpointを経由する。

### 5.3 派生データ

以下は再生成可能である。

- `transcript_text`
- URL抽出本文
- `title`
- `summary`
- `tags`
- `structured_data`
- Embedding

再生成時もCanonical Rawは変更しない。

---

## 6. 契約と境界

### 6.1 Raw DTO

概念上は次の型を持つ。PHP実装ではreadonly DTO + enumを推奨する。

```text
CapturedRaw
├─ TextRaw
├─ AudioRaw
└─ UrlRaw
```

共通項目:

```text
CapturedRaw
- client_capture_id
- user_id
- raw_kind
- capture_channel
- captured_at
- metadata
```

媒体固有項目:

```text
TextRaw
- text

AudioRaw
- stream / uploaded_file
- server_detected_mime
- original_filename
- declared_duration_ms

UrlRaw
- url
```

### 6.2 Capture Adapter

```php
interface CaptureAdapter
{
    public function toCapturedRaw(CaptureCommand $command): CapturedRaw;
}
```

Adapterの責務:

- 外部入力を型付きDTOへ変換
- 入口固有の形式検査
- `capture_channel` の確定

Adapterの非責務:

- AI呼び出し
- 文字起こし
- タグ生成
- Embedding生成
- 手紙生成

### 6.3 Canonical Raw Store

```php
interface CanonicalRawStore
{
    public function persist(CapturedRaw $raw): Memory;
}
```

責務:

- ownerを確定
- rawを失わず保存
- `client_capture_id` による冪等化
- text/urlなら `raw_content`
- audioならprivate Asset
- Memory IDを返す

### 6.4 Normalizer

```php
interface RawNormalizer
{
    public function supports(RawKind $kind): bool;
    public function normalize(Memory $memory): NormalizedContent;
}
```

実装:

- `TextRawNormalizer`
- `AudioTranscriptionNormalizer`
- `UrlContentNormalizer`

NormalizerはRegistryで選ぶ。ControllerやJob内の巨大な `switch` を増やさない。

### 6.5 Pipeline Orchestrator

```php
interface MemoryProcessingPipeline
{
    public function dispatchAfterCapture(Memory $memory): void;
    public function resumeFrom(Memory $memory, ProcessingStage $stage): void;
}
```

`source_type` ではなく `raw_kind` と現在の処理状態で次Stageを決める。

---

## 7. 入力別の処理

### 7.1 Webテキスト

1. 入力開始
2. IndexedDBへ保存
3. Serverへ同期
4. `raw_content` 保存
5. Enrich
6. Embedding

### 7.2 ブラウザ録音

1. 録音停止
2. 音声BlobをIndexedDBへ保存
3. Serverへ同期
4. private Assetへ原音声保存
5. 文字起こし
6. Enrich
7. Embedding

### 7.3 音声ファイル取込

1. ユーザーが既存ファイルを選択
2. server-side MIME / size検査
3. private Assetへ保存
4. Memory作成
5. 文字起こし
6. Enrich
7. Embedding

ファイル取込では原本が端末上に既に存在するため、24MB級Blobを必ずIndexedDBへ複製する必要はない。Server ACK前に「保存済み」と表示してはならない。

### 7.4 iOS Shortcut

1. Shortcutからtext/url/audioを送信
2. Scope付きTokenを検証
3. 対応AdapterでCanonical Raw保存
4. 以降は同じPipeline

iOSは入力経路であり、rawの種類ではない。

### 7.5 URL

1. 入力URL文字列をCanonical Rawとして保存
2. 非同期で本文抽出
3. 取得時刻・最終URL・HTTP結果を記録
4. Enrich
5. Embedding

Webページは将来変わり得るため、URL文字列と取得本文を同一の「事実」とみなさない。

---

## 8. 状態と再試行

既存の `status` と `transcription_status` を壊さない。

新規Stageの状態は各専用レコード側で管理する。

| Stage | 推奨管理場所 |
|---|---|
| Capture | Memory + IndexedDB queue |
| Transcription | 既存 `transcription_status` |
| Enrich | 既存 `status` / enrichment機構 |
| Embedding | `memory_embeddings.status` |
| URL normalize | URL metadataまたは専用status |

共通規則:

- Jobは冪等
- 条件付きUPDATEでclaim
- 一過性障害だけretry
- 恒久エラーは即failed
- 前段の成果物を再課金して作り直さない
- rawが保存済みなら「記録失敗」と表示しない
- 派生処理失敗は「整理待ち／整理失敗」と表示する

---

## 9. sensitiveとプライバシー

### 9.1 必須除外

`sensitive=true` は次からSQL段階で除外する。

- Embedding生成
- ベクトル検索
- Context Builder
- キオク便り
- Obsidian自動出力
- Clear Dawn / ヨユウへの自動候補

### 9.2 外部AI送信

既存仕様では、sensitiveは表出除外であり、初回の文字起こし・整理で外部AIへ送られる可能性がある。この意味を変更する場合は別CRとし、本変更へ混ぜない。

### 9.3 ログ

ログへ出さない:

- raw本文
- transcript
- URL本文
- 音声
- Embedding vector
- API key

ログへ出してよい:

- memory_id
- stage
- provider/model
- duration
- token/課金量
- error分類

---

## 10. 互換移行

### 10.1 Backfill案

既存値を監査してからマッピングする。

| 既存 source_type | raw_kind | capture_channel |
|---|---|---|
| `manual` | `text` | `web_text` |
| `url` | `url` | `web_url` |
| `voice` | `audio` | `browser_voice` |
| `yoyu` / `clear_dawn` / `ai_chat` | 原データを監査 | `system_connector` |
| `slack` | 原データを監査 | `system_connector` |
| `kioku_letter` | `text`相当 | `system_generated` |

不明な値を推測でbackfillしない。`raw_content` / Assetの有無で監査結果を出す。

### 10.2 ロールアウト

1. nullable列を追加
2. Backfill dry-run
3. Backfill実行
4. 新Captureのみ新列を必須化
5. 読み取りを新列優先・旧列fallbackへ
6. 十分な期間後に旧責務を縮小

---

## 11. UI要件

メイン画面:

```text
キオク
考えをそのまま残す。
AIが整理し、必要なときに思い出せる形にします。

┌──────────────────────────────┐
│ なんでも、まずここへ          │
│                              │
│ 思いついたこと、気づき、URL…  │
│                              │
│ [音声で残す] [ファイル取込] [保存] │
└──────────────────────────────┘

保存は即時。AI整理はあとから行います。
```

表示は次を区別する。

- 端末保存済み・同期待ち
- Server保存済み・整理待ち
- 文字起こし中
- AI整理中
- 意味検索準備中
- ready
- 派生処理失敗・rawは安全

---

## 12. 不変条件

1. テキスト原文は作成後に変更しない
2. 原音声は文字起こしで置換しない
3. URL抽出本文で入力URLを置換しない
4. Capture成功はCanonical Raw保存完了を意味する
5. AI成功をCapture成功条件にしない
6. 同じ `client_capture_id` の再送でMemoryを増やさない
7. 新しい入力元は既存の後段Jobを再利用する
8. `source_type` だけで処理分岐しない
9. sensitiveを意味検索・表出へ混ぜない
10. 派生データ変更後は古いEmbeddingをcurrent扱いしない

---

## 13. 完了条件

- 既存ブラウザ録音の動作が変わらない
- 音声ファイル取込とブラウザ録音が同じTranscription Jobへ到達
- text/audio/urlでCanonical Rawが定義どおり残る
- 再送で重複Memory/Assetができない
- 文字起こし失敗でも原音声を再生できる
- Enrich失敗でもrawを確認できる
- Embedding失敗でも全文・タグ検索を使える
- owner以外はraw/Assetへアクセスできない
- sensitiveがEmbedding対象にならない
- 既存キオク便り・タグ・検索が回帰しない

---

## 14. 非目標

- 常時録音
- Realtime会話AI
- 音声を直接マルチモーダルEmbedding化
- ユーザーごとのEmbeddingモデル再学習
- 小型レコーダー専用ドライバ
- URLクローラの大規模運用
- source_typeの即時削除
- すべてを一つの巨大interfaceへ統合

---

## 15. 設計判断の要約

```text
入口の違いはAdapterへ閉じ込める。
一次情報は媒体ごとに不変保存する。
文字化・整理・Embeddingは再生成可能なStageにする。
タグは人間の軸、Embeddingは機械の距離として併用する。
```

