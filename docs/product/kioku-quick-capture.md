# キオク クイックキャプチャ／音声入力 仕様

- 作成日: 2026-07-13
- 状態: 確定（QC-1〜QC-3 実装対象）
- 関連: [seed-k-personal-os.md](./seed-k-personal-os.md) / [kioku-scalability-audit-and-business-model.md](../kioku-scalability-audit-and-business-model.md) / [tables.md](../data/tables.md)

## 1. 目的

疲れた平日23時でも、思いついた一文・一言を **30秒以内に「失われない状態」（端末永続化）にする**。

不変条件:

> ユーザーが入力を終えた時点で、AI・ネットワーク・文字起こしの成功を待たず、元の思考を失わない状態にする。

- テキスト: 保存操作 → **端末保存（IndexedDB）** → サーバー同期 → Memory 保存 → enrich 非同期
- 音声: 録音停止 → **音声Blobを端末保存** → サーバー同期 → 原音声を非公開保存 + Memory 作成 → 文字起こし → enrich

AI・文字起こし・ネットワークの成功を待ってから raw を保存する実装は禁止。

## 2. 既存実装（このスペック以前に実装済み・作り直さない）

- `POST /kioku/memories`: raw_content のみ必須。title は「整理中…」プレースホルダ、type/tags/importance は AI が後段生成
- `EnrichMemoryJob`: 非同期 dispatch、tries/backoff/timeout、`ShouldBeUnique`、条件付き UPDATE による原子的 claim、失敗時 raw 保持、classify 再課金防止
- status ポーリング: `GET /kioku/memories/status` + `useKiokuStatusPoll`
- `POST /kioku/memories/{memory}/reenrich`（競合ガード付き）
- AI 追跡: `AiGateway` + `AiUsageRequest` / `AiUsageLog` / `AiUsageMonthly`、版付き `PromptTemplate`

## 3. 今回追加する差分

| ID | 内容 |
|---|---|
| QC-1 | `client_capture_id` 冪等性、JSON capture endpoint、IndexedDB 同期待ちキュー、計測イベント、raw_content 不変条件ガード |
| QC-2 | 音声録音 UI、音声 Blob の端末保存、`memory_assets`（原音声の非公開保存）、multipart upload、所有者認可付き再生 |
| QC-3 | `TranscriptionGateway`（差し替え境界 + fake）、`TranscribeMemoryAudioJob`、transcript の enrich 接続、検索対象への transcript_text 追加 |

## 4. source_type

既存値を維持し、`voice` のみ追加する。

- `manual` — 手動テキスト（**text へ rename しない**。既存データ移行なし）
- `url` — URL（manual 保存時に自動判定される既存挙動を維持）
- `voice` — 音声録音（新規）
- `yoyu` / `clear_dawn` / `ai_chat` / `slack` — 他プロダクト・コネクタ由来（既存のまま）

## 5. raw と派生データの責務

| フィールド | 責務 |
|---|---|
| `raw_content` | manual/url の canonical raw。**voice では null**（空文字で代用しない） |
| 音声 Asset（`memory_assets.kind = audio_original`） | **voice の canonical raw は原音声ファイル** |
| `transcript_text` | 音声から生成した**派生データ**。再生成可能。原音声を置き換えない |
| `summary` / `structured_data` / `tags` / `title` | AI 生成の派生データ（既存どおり） |

不変条件:

- `raw_content` は作成後に変更しない。Model の updating ガードで拒否する
  - ガード範囲: Eloquent Model 経由の update。Query Builder 直接 UPDATE は対象外（既存の直接 UPDATE 経路は status 等のみで raw_content に触れないことを確認済み。以後も raw_content を直接 UPDATE する経路を追加しない）
  - データ修復が必要な場合のみ `$memory->permitRawContentRepair()` を呼んでから update する（内部フラグ `allowRawContentMutation`）
- 文字起こし・enrich の失敗は記録の失敗にしない（raw と原音声は常に残る）
- transcription/enrich Job は raw_content・音声 Asset を書き換えない

## 6. 冪等性（client_capture_id）

- クライアントが `crypto.randomUUID()` で `client_capture_id` を生成し、端末保存時に確定する
- `memories.client_capture_id` は nullable、`(user_id, client_capture_id)` に unique 制約
- 同一 ID の再送はサーバー側で既存 Memory を返し、二重作成しない（同じ本文でも別 ID なら別 Memory）
- 既存 `POST /kioku/memories`（Inertia redirect）は後方互換のため維持。client_capture_id なしの保存も引き続き可能

## 7. IndexedDB 同期待ちキュー

1. 保存操作（テキスト保存 / 録音停止）で raw・client_capture_id・captured_at・計測値を IndexedDB へ永続化
2. 端末保存成功後に「同期待ち」として UI に即時表示（**IndexedDB 保存が失敗した場合は「保存済み」と表示しない**）
3. バックグラウンドで JSON capture endpoint へ同期
4. 成功時にキューから削除（音声 Blob も端末から削除）
5. 失敗時は保持し、online 復帰時・次回 Kioku Home mount 時に再送
6. 送信中 ID を管理し二重送信しない（同一タブ内。複数タブ間の排他は backlog）
7. 422/413 は terminal rejection（自動再送しない）。ユーザーが確認のうえ「端末から破棄」できる。pending/retryable は自動破棄しない

Service Worker / Background Sync API は使わない（非目標）。表示上「サーバー保存済み」と「端末保存のみ（同期待ち）」を区別する。

## 8. sensitive（現行仕様の維持）

- sensitive は **Recall／コンシェルジュ等の表出対象から除外する** フラグである（`RecallService` で除外）
- **sensitive でも enrich・文字起こしの際に原文・音声由来テキストは外部 AI へ送信される**（「sensitive なら AI に渡らない」とは宣伝できない — 監査 doc v1.1 と同一の立場）
- 今回この意味を変更しない

## 9. status と transcription_status

既存の単一 `status`（captured → enriching → ready / failed、他に archived）を全 source_type の総合ライフサイクルとして維持する。text 側に processing_version は導入しない。

voice のみ `transcription_status` を追加する:

| 値 | 意味 |
|---|---|
| null | manual/url 等、文字起こし不要 |
| `pending` | 文字起こし待ち（provider 未設定時もここに留まり、UI は「文字起こし未設定/保留」を表示） |
| `processing` | 文字起こし中（条件付き UPDATE で原子的に claim） |
| `ready` | transcript_text 保存済み → enrich へ接続 |
| `failed` | 失敗。原音声は保持。`status` も failed にし、retry で pending へ戻す |

競合制御は既存と同じ「条件付き UPDATE + ShouldBeUnique」を使う。generation/version 列は、この方式で防げない競合が実際に確認された場合のみ音声側に限定して検討する。

## 10. エンドポイント

| ルート | 用途 |
|---|---|
| `POST /kioku/captures/manual` | JSON。IndexedDB キューからのテキスト同期。冪等 |
| `POST /kioku/captures/voice` | JSON + multipart。音声 Blob 同期。冪等 |
| `GET /kioku/memories/{memory}/audio` | 所有者認可付き音声再生（非公開 storage から stream。公開 URL は保存しない） |
| `POST /kioku/memories/{memory}/retry-transcription` | transcription failed からの再実行 |
| `POST /kioku/memories` | 既存 Inertia フォーム互換（維持） |
| `POST /kioku/captures/events` | 計測イベント記録（本文は送らない） |

Memory 作成ロジックは `CaptureMemoryService` に集約し、既存 store と capture endpoint で共有する。分割 upload・署名付き直接 upload は必要性が確認されるまで導入しない。

## 11. 音声設定

- 最大録音時間: **3分**（クライアントの MediaRecorder timer が超過前に自動停止して安全に保存）
- サーバーはクライアントが申告する `duration_ms` が上限以内かを検証する（**実音声ファイルからの duration 解析は行わない**。解析は backlog）
- 最大ファイルサイズ: **20MB** 初期値（`config/kioku.php` で変更可能）。実時間制限の代替ガードとしても機能する
- 形式: 固定しない。`MediaRecorder.isTypeSupported()` で選択（Safari は audio/mp4、Chrome は audio/webm 想定）
- 保存 disk: `KIOKU_AUDIO_DISK`（既定 `local` = 非公開 private disk）。**本番は永続的な非公開 object storage を指定する。Laravel Cloud の一時ローカル領域を本番永続保存先にしない**
- サーバー側で MIME・サイズ・申告 `duration_ms`・所有者を検証する
- 権限拒否・MediaRecorder 非対応環境ではテキスト入力を案内する
- reduced motion 設定では録音アニメーションを抑制する

### 再生（audio endpoint）

- `GET /kioku/memories/{memory}/audio` は所有者認可付きの **200 ストリーム**（`Storage::response`）
- **HTTP Range / 206 Partial Content は保証しない**（Accept-Ranges の偽装もしない）
- MVP 保証: **先頭からの再生**
- シーク／スクラブ／途中再開は、本番 object storage + iPhone Safari の実機確認後に対応判断する
- iPhone Safari で先頭再生できない場合は本番公開 NO-GO
- 「Safari で完全再生対応済み」とは宣伝しない

### storage cleanup

| 削除経路 | storage 実体 |
|---|---|
| Eloquent `$memory->delete()` | `Memory::deleting` → Asset Eloquent delete → `MemoryAsset::deleted` でファイル削除 |
| アカウント削除（`ProfileController@destroy`） | **先に** `CleanupUserKiokuAudioService` が Asset の disk/path と `kioku-audio/{userId}/` 配下の孤児ファイルを削除。失敗時はアカウント削除を中断（黙って成功にしない）。その後 User 削除の FK cascade で DB 行を削除 |
| Query Builder / DB cascade のみ | Eloquent イベント不発火のため storage は消えない。通常フローでは使わない |

process crash（ファイル保存後・DB commit 前）の孤児ファイル sweeper は backlog。

## 12. 文字起こし provider

- `TranscriptionGateway` interface で抽象化。`config/kioku.php` の `transcription.provider`（env `KIOKU_TRANSCRIPTION_PROVIDER`、既定 `none`）で切り替える
- **実 provider は未決定**。`none` の場合は Job を dispatch せず、`transcription_status = pending` のまま「文字起こし未設定」を正確に表示する（成功を偽装しない）
- provider=none 中に作られた voice Memory は、原音声が保存済みでも Detail から開ける（MemoryCard は voice を常に遷移可能にする。enrich スピナーとクリック可能性は別概念）
- 実 provider を有効化したあと、滞留している pending を流すには運用コマンドを実行する:
  - `php artisan kioku:transcriptions:dispatch-pending`
  - `--dry-run` で対象確認、`--user=` で単一ユーザー限定可
  - provider=`none` のときは dispatch せず非0 で終了する
  - `ShouldBeUnique` + 条件付き claim により再実行しても危険な二重文字起こしにはならない
- テストは fake 実装を使い、CI から実 provider を呼ばない
- 実 provider 導入時は、利用量追跡を既存の AiUsage 台帳系へ統合すること（音声分課金の追跡形式はその時点で確定する）

## 13. 計測

`kioku_capture_events` に最小限のイベントを記録する。**raw 本文・transcript・音声内容は計測データへ含めない。**

- イベント: capture 開始 / 端末保存完了 / サーバー同期完了・失敗
- 属性: source_type、duration_ms（capture 開始→端末保存）、sync 結果、retry 回数

目的は「代表的な短文を30秒以内に raw 保存できるか」の検証。

## 14. 非目標（backlog）

- 外部 AI へ一切送らない private capture（sensitive の意味変更を含む）
- Gemini Live 型の常時会話・リアルタイム AI 音声応答
- ライブ（ストリーミング）文字起こし
- 波形表示・波形編集・無音カット
- note / Qiita / X 記事生成
- コンシェルジュ専用 UI・cron
- Service Worker / Background Sync
- OsShellLayout への全画面共通クイックキャプチャ
- manual → text の rename
- text 側への processing_version 導入
- 分割 upload・署名付き直接 upload
- 実音声ファイルからの duration サーバー解析
- 汎用 HTTP Range / 206 Partial Content（本番 storage + iPhone Safari 実機確認後に判断）
- process crash 後の孤児音声ファイル sweeper
- 複数タブ間の capture queue flush 排他
- 実 transcription provider 接続

## 15. 成功条件と中止条件

成功条件:

- raw 消失 0 件（最重要）
- 代表シナリオ（短文テキスト / 10〜20秒音声）で端末保存まで 30 秒以内
- AI・文字起こし失敗時も raw / 原音声が 100% 残る
- 再送で重複 Memory が発生しない

再設計・中止条件:

- raw 消失が 1 件でも再現したら、機能追加を止めて保存経路を修正する
- 音声 upload 失敗率が 10% を超え、1回の修正後も改善しなければ対象環境を限定する
- 30 秒を超える主因が必須 UI 操作なら、項目・画面遷移を削る

実機確認対象: iPhone Safari / デスクトップ Chrome。
iPhone Safari では先頭再生を必須確認する。シーク不可は既知制限。先頭再生不可なら本番公開しない。
