# AI機能 完成設計書 — main昇格前のPhase 1 ＋ 将来基盤

作成日: 2026-07-11 / 対象ブランチ: develop / ステータス: レビュー待ち
関連: [キオク監査・事業モデル](../kioku-scalability-audit-and-business-model.md) / [作業記録 2026-07-11](../dev/2026-07-11-kioku-queue-classify-worklog.md)

---

## 0. 現状棚卸し（コード確認済み・2026-07-11 develop時点）

### 実装済み ✅

| 領域 | 内容 | 根拠 |
|---|---|---|
| キオク保存→AI整理 | Queue Worker経由（afterResponse廃止済み）、Job冪等、classify v2（誤分類修正済み）、再整理、eval | PR #97/#99 |
| キオク検索・一覧 | LIKE検索＋type/source件数、washi UI、関連記憶 | `KiokuSearchService` |
| ヨユウToday骨格 | today/tasks/mind/chatタブ、タスクCRUD、マインドダンプ→キオク、AIチャット（Recall注入） | `Yoyu/HomeController` |
| ブリーフィング非同期化 | `GenerateYoyuBriefingJob`＋statusカラム＋ポーリング（`useYoyuBriefingPoll`） | PR #98 |
| 食事記録（手動） | food_items / meal_entries / nutrition_goals、PFC集計 | PR #78 |
| Product Switcher / Clear Dawn基盤 | マトリクス・ルーティン・メトリクス | 既存 |

### 未実装（残作業）❌

| # | 項目 | 現状 |
|---|---|---|
| A | **Googleカレンダー連携** | `MockCalendar` 固定値。connectorsテーブルはstub（token列なし） |
| B | **ブリーフィングの本物入力** | AI生成は実装済みだが入力がモック（予定・Clear Dawnの一手とも） |
| C | **余裕メーター・空き時間提案** | 未実装（コンセプトのみ） |
| D | **キオク一覧の自動更新** | ブリーフィングのポーリングは有り。キオクIndexは手動リロードのみ |
| E | **AI利用量表示** | ai_usage_logsに記録はあるが表示画面なし |
| F | **AI利用枠の予約制（監査F-2）** | `assertWithinQuota` はまだ毎回SUM＋競合あり |
| G | **Recall改善（監査F-4）** | countReference無効化のみ。毎表示LIKE走査・キャッシュなしは残 |
| H | **バーコード/成分表カメラ登録** | 未着手（food_itemsにbarcode列なし） |
| I | **ChatGPTインポート（過去の自分）** | 未着手 |

Phase 1スコープ = A〜F。G はPhase 1.5（デプロイ後すぐ）、H・I は本書で設計まで行い実装はPhase 2。

---

## 1. アーキテクチャ方針

- 既存境界を維持: `Domain/Kioku` / `Domain/Yoyu` / `Domain/Shared/AI`。新設は `Domain/Connectors`（外部サービス連携）と `Domain/Kioku/Archive`（インポート基盤）。
- **外部APIは必ずQueue Worker経由**（PR #97の原則を全連携に適用）。Webリクエスト中に外部APIを呼ぶのはOAuthリダイレクトのみ。
- **モックはインターフェースの後ろへ**: `CalendarProvider` interfaceを切り、`MockCalendar` はその一実装に格下げ。APIキー未設定のローカルでも全画面が動く状態を維持する。
- AI呼び出しは全て `AiGateway` 経由（利用量記録・上限判定の一元化）。新featureキー: `yoyu.briefing` / `meals.label_ocr` / `kioku.archive_answer` など。

---

## 2. Phase 1 設計（main昇格前）

### 2.1 Google Calendar連携（残作業A）

**スコープ: 読み取り専用（`calendar.readonly`）。書き込み・Gmailは対象外。**

#### 依存関係（要承認）

- `laravel/socialite` ＋ Google provider — OAuth флоwの標準実装。手組みも可能だがtoken refresh・エラー処理の再発明になるためsocialite推奨。**composer依存追加のため承認が必要**。

#### connectorsテーブル拡張（migration追加）

```
connectors（既存stubを拡張）
+ provider            string   'google'（source_typeと統合検討: 既存列を流用）
+ external_account    string   接続したGoogleアカウントのemail（表示用）
+ access_token        text     暗号化（encrypted cast）
+ refresh_token       text     暗号化（encrypted cast）
+ token_expires_at    timestamp
+ scopes              json
+ last_error          string nullable
  status: idle | connected | syncing | error | revoked
  unique(user_id, source_type)
```

**トークンは必ず `encrypted` cast**。ログ・例外メッセージにtokenを含めない（`__debugInfo` / `$hidden` 設定）。

#### イベントキャッシュテーブル（新規）

```
yoyu_calendar_events
- id            ulid PK
- user_id       FK
- connector_id  FK
- external_id   string
- title         string
- starts_at     timestamp
- ends_at       timestamp
- all_day       bool
- location      string nullable
- status        string（confirmed/cancelled）
- synced_at     timestamp
- unique(connector_id, external_id)
- index(user_id, starts_at)
```

APIを画面表示のたびに叩かず、**表示は常にこのキャッシュから**。鮮度はsynced_atで表示（「5分前に同期」）。

#### フロー

```
[接続] ヨユウ設定 → Googleへリダイレクト（state付き）→ callback
       → token保存（暗号化）→ SyncCalendarJob dispatch
[同期] SyncCalendarJob（Queue）:
       today−1日〜+7日のイベント取得 → upsert（external_id基準・冪等）
       → cancelled は status更新（削除しない）
       トリガー: 接続時 / ヨユウ表示時にsynced_atが15分超なら再dispatch（ShouldBeUnique: connector_id）
       / scheduler毎時（接続済みユーザーのみ）
[失効] 401/invalid_grant → status=error, last_error記録 → UIに「再接続してください」
       リフレッシュ成功時はtoken更新。**リトライでトークン再取得を乱発しない**（backoff）
[解除] ユーザー操作でrevoke API→token物理削除→イベントキャッシュ削除
```

#### インターフェース

```php
interface CalendarProvider {
    /** @return list<CalendarEvent> */
    public function eventsBetween(User $user, CarbonImmutable $from, CarbonImmutable $to): array;
}
// 実装: CachedGoogleCalendarProvider（キャッシュ読み）/ EmptyCalendarProvider（未接続）
//       / MockCalendarProvider（local/testingで YOYU_CALENDAR_DRIVER=mock 明示時のみ）
// bind: connected→Googleキャッシュ、未接続・失効→空snapshot＋接続CTA。
// production/stagingでMockへ自動フォールバックしない（架空予定をブリーフィングが
// 事実として扱う事故を防ぐ。詳細は implementation-plan §2.2 / §7.5）
```

### 2.2 ブリーフィング本実装＋空き時間提案（残作業B・C）

ヨユウの核。**「予定表示」ではなく「予定→分析→提案」まで**。

#### 入力の実データ化

`GenerateYoyuBriefingJob` の入力を差し替える:

| 入力 | 現状 | 変更後 |
|---|---|---|
| 予定 | MockCalendar | `CalendarProvider::eventsBetween(today)` |
| Clear Dawnの一手 | MockCalendar::clearDawnHand | `ClearDawnHandService`（新規）: 直近のmatrix_cell_items未完了＋life_areas優先度から1件選定。AI不要、クエリで決定 |
| 過去の経験 | RecallService（実装済み） | 変更なし（k=5上限維持） |
| タスク | なし | 未完了YoyuTask（上限20件・titleのみ） |

#### 空き時間分析はPHPで計算（AIに計算させない）

```
GapAnalyzer（新規・純粋関数）:
  入力: 当日イベント[], 稼働時間帯(既定 07:00-23:00)
  出力: [{start, end, minutes}] の空き一覧（30分以上のみ、最大5件）
```

計算はコードで確定させ、AIには**空き時間の使い方の提案文だけ**を書かせる。数値計算をLLMに渡すと間違えるため。

#### プロンプト（yoyu.briefing.v2）

```
入力: 予定（実データ・時刻付き）/ 空き時間リスト / Clear Dawnの一手 / 未完了タスク / 過去の経験（Recall5件）
出力（JSON）: {
  "overview":     "今日の全体像（1-2文）",
  "caution_time": "最も注意する時刻と理由",
  "hand":         "夢に向かう一手",
  "gap_suggestions": [{"slot":"14:00-14:30","suggestion":"..."}],  ← 空き時間ごと最大3件
  "let_go":       "手放していいこと",
  "pattern_note": "過去のパターンに基づく注意（Recallに根拠がある場合のみ）"
}
```

構造化JSONで保存（yoyu_briefings.structured_data json列を追加）し、UIはセクション別に描画。既存のbody（テキスト）はフォールバック表示用に残す。

#### 余裕メーター

AIではなく決定的な計算:

```
余裕度 = 1 − (予定合計時間 + タスク見積合計(上限4h)) / 稼働時間
表示: ゆったり(>0.5) / ちょうどいい(0.2-0.5) / 詰まり気味(<0.2)
```

ブリーフィングJobの結果と同時に算出しstructured_dataへ格納。

#### 移動時間・出発時刻（確定 2026-07-11）

カレンダーはタイトル・時刻・locationのみ読む。**移動時間はアプリ側の場所マスタ `yoyu_places`（既存: name / travel_minutes）で管理**し、カレンダー側に移動ブロックを入れさせない。Maps APIはMVPで不使用（画面注記どおり）。

- **マッチング**: イベントの `location` を正規化（trim・空白除去・大文字小文字と全角/半角を無視）した**完全一致**で `yoyu_places.name` を引く。部分一致はしない（誤マッチ防止）。解決はサーバー側で行い、フロントへは `travel_min: int|null` として渡す
- **未解決（location空 or 場所未登録）**: `travel_min = null`。**出発時刻は表示しない**（0分と偽らない — 架空値を事実として扱わない原則）。イベント行に「移動時間未登録」＋その場で分数を登録できる導線を出し、登録すると `yoyu_places` にupsertされ次回から自動解決
- **出発時刻** = 開始 −（移動 + 支度10分 + 余白5分）。支度・余白はMVPでは固定定数（`resources/js/lib/yoyuCalc.ts` の `PREP_MIN` / `BUFFER_MIN`）。ユーザー設定化はPhase 2
- **GapAnalyzer・余裕メーターへの反映**: `travel_min` が解決できたイベントは開始を（移動＋支度＋余白）分前倒ししてbusy扱い（稼働時間にclamp）。未解決イベントは前倒しなし。現行フロント計算（yoyuCalc）と同じ挙動をサーバーへ引き継ぐ
- **場所マスタUI**: PR-D1にイベント行からのインライン登録のみ含める。一覧・編集の専用画面はPhase 2

#### 自動生成

Phase 1では**当日初回アクセス時に自動dispatch**（briefingが無ければ）＋手動再生成ボタン（実装済み）。schedulerでの早朝プッシュ生成はPhase 2（通知手段がまだ無いため無意味）。

### 2.3 キオク一覧の自動更新（残作業D）

ブリーフィングで作ったポーリングパターンを流用。監査Iの要件を満たす設計:

```
GET /kioku/memories/status?ids[]=...   ← 新設・軽量エンドポイント
  - WHERE id IN (...) AND user_id = ?（上限50件・超過は422）
  - 返却: [{id, status}] のみ（raw_content等は返さない）
  - index(user_id, status) が効く

フロント（useKiokuStatusPoll composable新規・useYoyuBriefingPollを一般化）:
  - captured/enriching のIDがある時だけ起動
  - 間隔3秒→5秒→8秒とバックオフ、最大3分で停止（手動リロード案内）
  - document.hidden 中は停止、復帰で再開
  - 全件ready/failedになったら router.reload({only:['memories']}) を1回だけ実行し停止
```

将来Echo/Reverbへ移行する場合もこのエンドポイントとcomposableの差し替えだけで済む（境界確保）。

### 2.4 AI利用量表示 ＋ F-2予約制（残作業E・F — 同一PRで実装）

監査F-2の予約・確定・解放とUI表示は同じテーブルを使うため一体で実装する。

#### スキーマ

```
ai_usage_monthly
- id           ulid PK
- user_id      FK
- period       char(7)  '2026-07'
- spent_usd    decimal(10,4) default 0
- reserved_usd decimal(10,4) default 0
- unique(user_id, period)

ai_usage_requests（二重確定防止＋監査）
- id           ulid PK（= usage_request_id）
- user_id / feature / model
- estimated_usd / actual_usd nullable
- status       reserved | settled | released
- created_at / settled_at
- index(user_id, created_at)
```

#### AiGateway改修

```
complete():
  1) $requestId = ULID発行
  2) 予約: UPDATE ai_usage_monthly SET reserved_usd = reserved_usd + :est
       WHERE user_id=? AND period=? AND spent_usd + reserved_usd + :est <= :limit
     （行が無ければfirstOrCreate後に再試行。0行更新→QuotaExceededException）
     :est = maxTokens×出力単価 + 入力見積×入力単価（保守的に）
  3) API呼び出し
  4) 成功: 同一トランザクションで reserved−=est, spent+=actual, requests.settled
     失敗: reserved−=est, requests.released
  5) ai_usage_logs明細は現行どおり（表示はmonthlyから読むためSUM廃止）
```

孤児予約対策: `settled_at is null かつ created_at < 10分前` のreservedをschedulerで解放（Workerクラッシュ対応）。

#### UI（ヨユウ/キオク設定 or 共通設定に「AI利用量」セクション）

```
今月のAI利用          $0.75 / $10.00 上限
  Sonnet   $0.63  ████████░░
  Haiku    $0.12  █░░░░░░░░░
機能別: キオク整理 42回 / ブリーフィング 8回 / チャット 15回
80%到達で画面上部にバナー表示
```

ai_usage_monthly 1行読むだけなのでどこに置いても軽い。

---

## 3. 食事のバーコード/成分表カメラ登録（残作業H — Phase 2先頭・設計確定）

あすけんの仕組み: JANコード→自社整備の商品DB照合（外部から同じDBは使えない）。本プロダクトの現実解は**「公開DB＋AI OCR＋自分のDBが育つ」**構成。

```
[スキャン] バーコード読取（クライアント）
   BarcodeDetector API（Chrome/Android native）＋ fallback: @zxing/browser（依存追加・要承認）
      ↓ JANコード
[照合1] 自分のfood_items（barcode列を追加・要migration）      → hit: 即登録
[照合2] Open Food Facts API（無料・要属性表示。日本商品のカバー率は限定的）→ hit: 確認画面→food_items保存
[照合3] miss → 成分表カメラへ誘導
[カメラ] 栄養成分表示を撮影 → AiGateway（vision, feature=meals.label_ocr, tier=cheap）
   プロンプト: 「栄養成分表示から JSON {serving_label, kcal, protein_g, fat_g, carb_g, per:'serving|100g'} のみ」
   → 確認フォーム（AI結果は必ずユーザー確認を挟む。誤読リスクがあるため自動確定しない）
   → food_itemsへ保存（barcodeがあれば紐づけ）→ 次回からスキャンだけでhit
```

- 原価: 画像1枚 Haiku vision ≈ $0.001〜0.005。AiGateway経由なので月次上限・利用量表示に自動で乗る。
- 写真は解析後に破棄（保存しない。保存するならユーザー明示同意＋private storage）。
- 一度登録した商品が自分のDB資産になる＝使うほど速くなる。キオクと同じ「蓄積が価値」の思想。
- **やらないこと**: 商品DBの外部購入、料理写真からの推定（精度が成分表OCRと段違いに悪い）。

---

## 4. ChatGPTインポート／「過去の自分」基盤（残作業I — Phase 2〜3・設計確定）

### 4.1 位置づけと原則

- connectors第2弾。ただしAPI連携ではなく**ファイルアップロード**（ChatGPT公式エクスポートの conversations.json）。
- 原則: **原文は自分のDBに保管し、AIには検索で絞った断片だけ送る**（RAG）。全文をAIに預けない。
- 原文とAI解釈は必ず分離保存（AIの誤読を後から検証可能に）。

### 4.2 スキーマ

```
chat_conversations
- id ulid PK / user_id FK / connector_id FK nullable
- external_id string / source string('chatgpt'|'claude'|...)
- title / started_at / updated_at_source
- raw_json_path string（private storageの元ファイル位置）
- unique(user_id, source, external_id)   ← 再インポート冪等性の要

chat_messages
- id ulid PK / conversation_id FK / user_id FK（シャーディング境界のため非正規化で持つ）
- external_id / parent_external_id
- role string('user'|'assistant'|'system'|'tool')
- content longtext / sent_at / sequence int
- unique(conversation_id, external_id)
- index(user_id, sent_at)

memory_chunks（検索単位）
- id ulid PK / user_id FK / conversation_id FK / message_ids json
- chunk_index int / content text（目安600〜1200トークン。user発言+直後のassistant回答をペアで1チャンク）
- speaker string / occurred_at timestamp
- sensitivity_level tinyint default 1
- token_count int / embedding ※後述 / embedding_model string nullable
- index(user_id, occurred_at) / index(user_id, sensitivity_level)

memory_insights（Phase B。fact/opinion/emotion/goal/decision/reason/concern/advice/action/result/learning/idea）
- 監査ドキュメントの設計どおり。superseded_by_idで考えの変遷を連結

ai_requests（外部送信ログ — 何を外に出したかの完全な記録）
- id ulid / user_id / provider / purpose / model
- sent_chunk_ids json / redacted_prompt_hash string / sent_at
- index(user_id, sent_at)
```

**embeddingの保存先（ADR要作成）**: 現DBはMySQL系。選択肢は (a) MySQL 9 VECTOR型 / (b) Laravel CloudのPostgres+pgvectorを**アーカイブ専用の別DBコネクション**で追加 / (c) 外部Vector DB。推奨は(b) — キオク監査で定義した「検索基盤の分離境界」を最初からこの機能で実践でき、本体DBに数十万chunkを持ち込まない。**Phase Aはembedding無しで開始**するため、この決定はPhase B着手時まで遅延できる。

### 4.3 インポートパイプライン（数百MB・数千会話を想定）

```
UploadController: conversations.json → private storage保存のみで即応答（分析はしない）
  ↓
ImportChatArchiveJob（Queue, timeout長め, ShouldBeUnique: connector）
  - ストリーミングJSONパース（halaxa/json-machine 依存追加・要承認。メモリに全ロードしない）
  - 会話単位でchunked insert（500件バッチ）
  - 冪等: unique(user_id, source, external_id) + upsert。再アップロードは差分のみ
  - 進捗: connectors.status='syncing' + imported_count を更新（UIポーリングはキオクIndexと同じcomposable）
  - 失敗時: 途中まではコミット済みでOK（会話単位で完結）。再実行で続きから
  ↓
ChunkConversationJob（会話ごと・バッチQueue）
  - user発言+assistant回答ペアでchunk生成、長文は分割
  - sensitivity初期判定（ルールベース: 正規表現でAPIキー/メール/電話→自動でlevel上げ）
  ↓（Phase B）
EmbedChunksJob — embedding生成（ローカルモデル or API。ai_usage_logsに記録）
```

**1ユーザーの大量インポートで全体を詰まらせない**: このパイプラインは専用queue（`queue: archive`）に分離し、キオク整理・ブリーフィングの`default` queueと別Workerで処理（監査Hのバックプレッシャー要件）。

### 4.4 質問→回答フロー（Phase A: embeddingなしで成立させる）

```
質問 →（cheap AIで主題・期間・種類を抽出 or まずは素朴にキーワード分解）
  → SQL検索: WHERE user_id=? AND occurred_at BETWEEN ? AND sensitivity_level <= 3
     AND キーワード（title/content。件数上限100・LIKEでも期間で絞れば実用）
  → 再ランキング（PHP: キーワード一致40%/日付関連30%/本人発言20%/長さ補正10%）
  → 上位10〜30チャンク
  → MaskingService: メール/電話/APIキー/トークン/URL認証部を [REDACTED_*] へ置換
     level 4（外部送信禁止）は**この時点で必ず除外**（検索でヒットしても送らない）
  → ai_requests記録 → Sonnetへ「引用日付つきで回答。記録にないことは推測しない」
  → 回答＋出典（チャンク→元会話へのリンク）
```

Phase Bでハイブリッド化（キーワード＋ベクトル）と memory_insights 抽出を追加。**キーワード検索を捨てない**（固有名詞・数値はベクトルより強い）。

### 4.5 プライバシー設計（キオク全体の共通基盤に昇格）

- sensitivity_level 0〜4 を **memoriesテーブルにも将来展開**（現在のsensitive booleanの上位互換。移行: sensitive=true → level3）
- level 4 = いかなるAI呼び出しにも含めない（AiGateway手前でガード）
- MaskingServiceはEnrichMemoryJob・ブリーフィング・チャットにも順次適用
- ai_requests（送信ログ）はChatGPTアーカイブ専用ではなく**AiGateway共通機能**として実装（Recall注入・ブリーフィングも記録対象）→ 監査ドキュメントのrecall_logs構想と統合
- 事業モデル文書の表示原則を遵守: 「Anthropicへ送信・標準で最大30日保持」を設定画面に明記

---

## 5. 実装順序（PR分割）とデプロイタイミング

ユーザー方針どおり: **Switcher✅ → キオク✅ → ヨユウToday → Google Calendar → AIブリーフィング → テスト環境デプロイ**。

| PR | 内容 | 依存 | 規模 |
|---|---|---|---|
| PR-A | F-2予約制＋AI利用量表示（§2.4） | なし | 中 |
| PR-B | キオク一覧自動更新（§2.3） | なし | 小 |
| PR-C | Google OAuth＋connectors拡張＋SyncCalendarJob＋CalendarProvider interface（§2.1） | socialite承認 | 大 |
| PR-D | ブリーフィングv2（実カレンダー＋ClearDawnHandService＋GapAnalyzer＋余裕メーター＋structured UI）（§2.2） | PR-C | 大 |
| **→ テスト環境デプロイ**（Laravel Cloud設定: CACHE_STORE/QUEUE_CONNECTION/Worker/GoogleクレデンシャルENV） | | | |
| PR-E | Recall改善（監査F-4: SQL側フィルタ・短期キャッシュ・ai_requests統合） | PR-A | 中 |
| PR-F | バーコード＋成分表OCR（§3） | zxing承認 | 中 |
| PR-G以降 | ChatGPTインポートPhase A（§4.2-4.4）→ Phase B（embedding/insights） | json-machine承認・ADR | 特大 |

Phase 2（Gmail・出発時間・LINE通知）、Phase 3（Slack/GitHub/Cursorコネクタ）はこの後。**今やらない**: pgvector（Phase Bまで遅延）、WebSocket（ポーリング境界で分離済み）、Maps API、ベクトル検索。

## 6. 障害対応・運用チェックリスト（Phase 1完了時点）

- Google API障害時: イベントはキャッシュ表示（synced_at明示）、ブリーフィングは「予定情報が古い可能性」注記付きで生成続行
- token失効: UIバナー「再接続」。自動リトライでGoogleへ連打しない
- Queue滞留監視: `queue:monitor database:default --max=100` をscheduler＋失敗時ログ
- AI上限到達: 保存・表示は継続、AI機能のみ「今月の上限に達しました」（PR-Aで実装）
- 全連携の資格情報はENV＋暗号化cast。リポジトリ・ログに残さない

## 7. 未決事項（承認・判断待ち）

1. composer/npm依存の追加承認: `laravel/socialite`、`halaxa/json-machine`、`@zxing/browser`
2. embedding保存先ADR（§4.2 — Phase B着手時までに）
3. Google Cloud Console側のOAuthクライアント作成（redirect URI: test環境ドメイン確定後）
4. sensitivity_level 5段階のmemoriesへの展開時期
