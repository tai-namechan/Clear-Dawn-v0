# キオク ナレッジ検索・Context Builder 基盤

- 作成日: 2026-07-16
- 状態: 確定(feat/kioku-tag-context-retrieval で実装)
- 関連: [kioku-quick-capture.md](../product/kioku-quick-capture.md) / [kioku-final-remaining-implementation.md](../product/kioku-final-remaining-implementation.md) / [kioku-concierge-daily-pilot.md](../product/kioku-concierge-daily-pilot.md)

## 1. 論理三層モデル

キオクの1件のMemoryは、次の三層で扱う。**物理的なrawフォルダ/整理済みフォルダは作らない。**

| 層 | 内容 | 更新 |
|---|---|---|
| 事実層 | manual/url の `raw_content`、voice の `memory_assets` 原音声 | **作成後不変**(既存のEloquent更新ガード) |
| 解釈層 | `transcript_text` / `title` / `summary` / `memory_type` / `tags` / `structured_data` / `importance` | AIまたはユーザーが更新可能 |
| ビュー/利用層 | 検索結果・Recall・コンシェルジュ手紙・タグ別ビュー・将来の動的階層 | 保存しない(クエリ/表示時に構成) |

### 物理フォルダを採用しない理由

- 1件のMemoryは複数の文脈(例:「Vite」「エラー」「仕事」)に同時に属する。フォルダは1箇所への所属を強制し、**複製かリンク切れ**のどちらかを生む
- 事実層は不変であり、移動・整理の対象にしてはいけない。「整理」はすべて解釈層(tags)とビュー層で行う
- ビューは使い捨てできる。フォルダ構造をDBに持つと、その構造自体がsource of truth化して再整理をブロックする

したがって:

- **1つのMemoryは複数タグ・複数ビューに属する**(複製は作らない。すべて同じMemory Detailへリンクする)
- **タグは保存先ではなく、検索・分類のためのメタデータ**である(解釈層。付け替えてもrawは不変)
- **AI生成の階層・グルーピングはsource of truthではなく一時的なビュー**である(今回は保存もしない。タグ別ビューはクライアント側で現行レスポンスから構成する)

## 2. タグ正規化(KiokuTagNormalizer)

AI分類(`MemoryClassifier`)とユーザー手動更新の両方が、同じ純粋サービス
`App\Domain\Kioku\Services\KiokuTagNormalizer` を通る。

規則(入力順に適用):

1. 文字列以外の要素は捨てる
2. 前後の空白(半角/全角)を除去
3. 連続する空白(半角/全角)を半角スペース1つへ統一
4. 先頭の `#` / `＃` を除去し、再トリム
5. 空文字は除外
6. **41文字以上のタグは捨てる**(切り詰めない。切り詰めは別タグ同士を同一化させるため)
7. 大文字小文字を無視して重複排除し、**最初に現れた表記を表示値として維持**
8. 入力順を維持(安定順序)
9. 最大8タグ(9個目以降は捨てる)

- Unicode正規化(NFKC等)のための新規依存は追加しない(`mb_strtolower` での大小同一視まで)
- 既存データの一括migrationは行わない。検索は「保存されている値との配列要素一致」なので、旧表記のタグもそのまま検索・表示できる(read-time正規化で十分なため補正commandも作らない)

## 3. タグ検索(Kioku Home)

URLクエリ: `q` / `types[]` / `tags[]` / `tag_mode=and|or`(既定 `and`。andのときはURLから省略)

- `tags[]` は**JSON配列要素としての完全一致**(`whereJsonContains`)。タグ名の部分一致では絞り込まない(「ヨガ」で「ヨガ教室」はヒットしない)
- MySQLは `JSON_CONTAINS`、SQLite(テスト環境)は `json_each` にコンパイルされる(Laravel標準grammar)
- AND = 指定タグをすべて持つ / OR = いずれかを持つ
- 既存の `q` / `types[]` とは併用(すべてAND結合)。後方互換を維持
- owner境界(user_id)は従来どおり
- 表示切替「時系列 / タグ」はビュー層のみ。タグビューは**現行レスポンス(最大100件)からクライアント側でグループ化**し、同じMemoryが複数タグのグループへ同時に現れる。タグなしは「未分類」。追加のDB読み込み・AI呼び出しはしない
- タグ件数の表示は、現在ユーザーに見えているレスポンス内からのみ集計する

## 4. Context Builder(AI向け取得)

`App\Domain\Kioku\Services\KiokuContextBuilder` が、AIへ渡す記憶の取得を一元化する。
**全記憶をAIへ渡さず、現在の質問に関連する記憶だけを件数・文字数上限内で渡す。**

### 入力

| 引数 | 既定 | 意味 |
|---|---|---|
| userId | 必須 | 所有者 |
| query | 必須 | 現在の文脈テキスト |
| tags | [] | 明示タグ(正規化してから使用) |
| seedMemoryIds | [] | 起点Memory(memory_links経由の関連を加点。seed自身は結果から除外) |
| topK | 5 | 最大件数 |
| maxChars | 4000 | 合計文字数上限(title+excerpt) |

### 除外(すべてSQL段階)

- 他ユーザーのMemory(`user_id`)
- `status != ready`
- **`sensitive = true`**(AI向け候補はDBクエリ段階で除外する。PHP側に到達させない)
- `source_type = kioku_letter`(手紙評価ログ。既存方針の踏襲)
- クエリ・タグ・seedリンクのいずれにも一致しない行(候補プールに入らない)

候補はSQLで**最大50件**(`importance DESC, captured_at DESC, id ASC` で切る)。全MemoryをPHPへ読み込まない。

### スコア(決定的・テスト可能)

| シグナル | 点 |
|---|---|
| 明示タグの配列要素一致 | +8 / タグ |
| query termがtitleに含まれる(大小無視) | +4 / term |
| summaryに含まれる | +2 / term |
| raw_contentまたはtranscript_textに含まれる | +1 / term |
| seedとmemory_links(双方向)で関連 | +3 |

- termは空白(半角/全角)区切り。日本語の完全な形態素解析はしない(現行検索と同じ前提)。**タグ完全一致(+8)を最強のシグナル**として扱う
- importanceはスコアへ加点しない。**同点時のtie-breakとして使用**: `importance DESC → captured_at DESC → id ASC`(importanceは1〜5の狭い範囲でシグナルより弱いため、加点でなくtie-breakに置いた)
- 同じMemoryは重複しない(SQLで一意)

### 返却(payload)

`memory_id / title / excerpt / tags / score / reasons / captured_at` のみ。
**raw全文・transcript全文は返さない。** excerptは `summary`、無い場合のみ現行Recall仕様と同じ
`mb_substr(raw_content ?? transcript_text, 0, 200)`。

- topKとmaxCharsの両方を必ず守る(超過した時点で打ち切り)
- 0件は正常系
- 本文・transcriptをログへ出さない
- トークナイザ依存は追加しない(今回はmaxCharsで管理)

### RecallServiceとの関係

`RecallService` の候補取得はContext Builderへ委譲する。公開interface(`for()` / `memories()`)、返却形式、最大件数、`referenced_count` 加算(1回のRecallで同じMemoryへ二重加算しない)、sensitive除外、AI台帳、PromptTemplateは従来どおり。取得理由(reasons)は `contextItems()` で内部追跡できる。

### コンシェルジュ手紙との関係

`KiokuLetterCandidateService`(14日cooldown・last_delivered_at・dedupe等の独自規則)は**置換しない**。共有するのはタグ正規化のみ。

## 5. 今回の非目標

- raw/structuredの物理フォルダ・Memory複製・タグ専用テーブル(memory_tags)への正規化
- pgvector / embedding / ANN / 外部ベクトルDB / 新LLM provider
- AIによる多段階階層の生成・保存
- 全記憶を毎回AIへ渡すこと
- raw_content・原音声の変更、transcript再生成、reenrichの大規模変更
- memory_interpretations / Replay(非破壊再整理・解釈の版管理は **CR-001** の独立PRで扱う)
- コンシェルジュpilotの仕様変更、OsShell全体へのUI追加、Yoyu/Videos/Calendar変更

## 6. 評価指標

- Recall/検索の体感精度: タグ一致が上位に来るか(手動確認)
- `reasons` により「なぜこの記憶が出たか」を説明できるか
- Context Builder経由のAI入力文字数が maxChars を超えないこと(テストで保証)
- タグ検索AND/ORの正答性(テストで保証)
- 回帰なし: 既存q/types検索・Recall返却形式・コンシェルジュ選定

## 7. 将来ベクトル検索を検討する条件

次の**すべて**が確認されたときだけ、hybrid(タグ+全文+ベクトル)を設計する。

1. 記憶が概ね1,000件を超え、LIKE+タグの取りこぼし(言い換え・同義語)が体感で頻発する
2. reasonsベースの評価で「タグ・全文一致では取れないが必要だった記憶」が具体例として蓄積される
3. embedding生成・保存の追加コスト(単価と運用)が月間AI予算内に収まる見込みがある

その場合も、本docの三層モデル・sensitive SQL除外・topK/maxChars上限はそのまま適用する。
