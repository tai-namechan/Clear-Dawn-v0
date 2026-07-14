# キオク基盤ギャップ整理 — 現状 / 設計予定 / やりたいこと

状態: **確定（改訂）**（2026-07-14）  
起点会話: 「海馬の外付け」ゴール定義と、シニア / PM / アーキテクト視点の検証  
関連:

- [seed-k-personal-os.md](../product/seed-k-personal-os.md)（decision スキーマ確定）
- [kioku-quick-capture.md](../product/kioku-quick-capture.md)（QC-1〜QC-3 実装済み）
- [ai-features-completion-design.md](./ai-features-completion-design.md) / [ai-features-implementation-plan.md](./ai-features-implementation-plan.md)
- [kioku-scalability-audit-and-business-model.md](../kioku-scalability-audit-and-business-model.md)
- [progress.md](../progress.md)（SK2/SK3/SK5 = done）
- リポジトリ外のキオク設計書（§5 raw 不変 / §7 Recall / §16 着手トリガー）※本リポジトリには未移植。本ドキュメントの二段トリガーが §16 の置き換え正

---

## 1. 一文で言うと

| 層 | 中身 |
|---|---|
| **現状** | 保存・AI整理・受動検索・ヨユウへの Recall 注入に加え、**クイックキャプチャ（QC-1〜QC-3）まで実装済み**。IndexedDB 同期待ち・冪等化・音声 raw 保存・raw 不変ガード・transcription 境界まで揃っている |
| **設計予定** | Recall 改善 → ChatGPT 取込 / embedding → 週次パターン発見など。能動プッシュは「第二段」 |
| **やりたいこと** | 海馬と同じ土俵では戦わず、苦手5つだけを外付けする。核心は **自動発火** と **領域またぎ** |

検証の発見: **存在理由の核心（自動発火・領域またぎ）は、まだプロダクト機能としては未実装。** ただし入力基盤（失わない保存）は QC で揃った。残ギャップは「いつ・何をトリガーに能動想起を検証するか」であり、本ドキュメントで **二段トリガー** として確定する。

---

## 2. ゴール定義（decision として保存可能な形）

今日の議論は、既存 `memory_type: decision` の `structured_data` に **不足なく収まる**（スキーマ追加不要）。これはデータモデル妥当性の証拠。

| フィールド | 内容（要約） |
|---|---|
| title | 記憶基盤キオクのゴールは「海馬の外付け」 |
| situation | 海馬に勝てない感覚 → プロダクトの存在意義への迷い |
| constraints | 連想/文脈/直感では勝てない・個人開発・堀は10年分の自分データ |
| options | A 海馬再現（却下）/ B 苦手だけ外付け（採用） |
| decision | 劣化コピーではなく外付け HDD。苦手5つのみ肩代わり |
| reason | 同じ土俵で「より上手く」やろうとした瞬間だけ負ける（電卓と暗算） |
| review_condition | **第一段**: コンシェルジュ4週間実験で「自動発火」に価値があるか。**第二段**: 記憶が貯まった時点（目安100件超）で領域またぎ週次バッチを評価 |
| tags / importance | 記憶基盤, ゴール定義, 海馬, 設計思想 / 5 |

> 実装メモ: `DecisionType` の required は `situation` / `decision` / `reason`。`constraints` / `options` / `review_condition` は任意だが seed-k では `review_condition` を必須方針としている。手動保存 or enrich 後の確認で揃える。

---

## 3. 海馬の苦手5つ ↔ キオクの対応

| 苦手 | 意味 | 現状 | 設計予定 | やりたいこととの差 |
|---|---|---|---|---|
| **正確さ** | 書き換わらない原文 | `raw_content` 保存。`Memory` updating ガードで作成後の変更を拒否（`permitRawContentRepair()` のみ例外）。voice は原音声 Asset が canonical raw | 設計書の raw 不変原則 | ✅ **合致**（QC-1 でコード不変条件化済み） |
| **持続** | 10年残る | `memories` 永続化。音声は非公開 disk + 所有者認可再生 | アーカイブ / Hot-Warm-Cold（監査ドキュメント） | △ 永続はある。10年運用（退避・再enrich・ストレージ階層）は未着手 |
| **自動発火** | 意志なしで思い出す | ❌ なし（ユーザーが検索 / ヨユウが文脈テキストで引くだけ） | 第一段: コンシェルジュ実験。第二段: 領域またぎバッチ | ⚠️ **最大ギャップ**。入力基盤は揃った。価値検証が次 |
| **感情フィルタ** | 必要な時だけ感情を出す | `emotion` type・`sensitive`（Recall / コンシェルジュ表出から除外） | 感度 0–4（ChatGPT 取込 Phase） | △ 型はある。文脈に応じた出し分けは未設計 |
| **領域またぎ** | 仕事×野球など横断相関 | `memory_links` はタグ/タイトル語の関連（最新100件上限） | 第二段トリガー（週次パターン / insights） | ⚠️ **第二のギャップ**。リンク≠領域横断の発見 |

付帯:

| 概念 | 現状 | 設計 | 差 |
|---|---|---|---|
| リプレイ（固定化） | `captured → enriching → ready` 二段。voice は `transcription_status` 追加 | 同左 | ✅ |
| クイックキャプチャ | QC-1〜QC-3 実装済み（下記 §4） | [kioku-quick-capture.md](../product/kioku-quick-capture.md) | ✅ 入力摩擦低減は達成。残は実 transcription provider |
| 受動 Recall | ヨユウ Today/Chat に最大5件注入 | PR-E で SQL 前処理・cache・計測 | 品質・性能の改善は予定あり。能動化は別物 |
| ゴール記憶自体 | decision として保存可能 | スキーマ確定済み | ✅ 保存オペレーションは人間側（§9 で実施を決定） |

---

## 4. 三層比較（機能単位）

凡例: ✅ あり / △ 部分 / ❌ なし / 📋 設計のみ / 🎯 やりたいこと側の重点

| 機能 | 現状（コード） | 設計予定（docs） | やりたいこと |
|---|---|---|---|
| 手動キャプチャ（原文だけ投げる） | ✅ `/kioku` store + `POST /kioku/captures/manual` | SK2/SK3 / QC-1 done | ✅ 入力摩擦を低く保つ（構造化は後段AI） |
| IndexedDB 同期待ちキュー | ✅ `kiokuCaptureDb` + `useKiokuCaptureQueue` | QC-1 | ✅ ネットワーク前に端末永続化 |
| 冪等化（`client_capture_id`） | ✅ `(user_id, client_capture_id)` unique | QC-1 | ✅ 再送で二重作成しない |
| raw 不変ガード | ✅ Model updating 拒否 + repair 明示オプトイン | QC-1 / 設計書 §5 | ✅ |
| 音声 raw 保存 | ✅ MediaRecorder → IndexedDB → `memory_assets`（非公開） | QC-2 | ✅ voice の canonical raw = 原音声 |
| transcription 境界 | ✅ `TranscriptionGateway` + Job + fake/null。**実 provider のみ未接続** | QC-3 | △ 境界まで完成。本番文字起こしは backlog |
| 型レジストリ（9 type + decision） | ✅ `MemoryTypeRegistry` | 確定 | ✅ decision で思想を保存 |
| AI classify / extract | ✅ `EnrichMemoryJob` | 同期AI禁止・Queue原則 | ✅ |
| 再 enrich（派生作り直し） | ✅ `reenrich` | モデル進化時の再処理と相性良い | 🎯 「派生はいつでも全部作り直せる」を徹底したい |
| 一覧・検索（LIKE） | ✅ 上限100・F-3課題あり。transcript_text も検索対象 | PR-E 改善 | 受動基盤としては必要だがゴールの本丸ではない |
| 関連記憶リンク | ✅ 最新100件スコア | 品質天井の指摘あり | 領域またぎの手前段階 |
| ステータス poll | ✅ PR-B | 完了 | 入力体験の必須部品 |
| ヨユウへの受動 Recall | ✅ `RecallService` | PR-E で強化 | △ 「呼ばれたら返す」まで。撃ち込みではない |
| ブリーフィング pattern_note | ✅/△ D2 系（根拠ある時のみ） | structured v2 | △ 予定文脈の注意であり、記憶の自動発火ではない |
| **能動プッシュ（コンシェルジュ）** | ❌（専用 UI・cron は非目標のまま） | **第一段: 4週間手動実験（本ドキュメント §7）** | 🎯 **今週から検証する核** |
| **週次パターン発見（領域またぎ）** | ❌ | **第二段トリガー**（件数が貯まった後） | 🎯 核。バッチ本番は第二段 |
| ChatGPT 取込 / chunks / embedding | ❌ | PR-G Phase A→B | データ量を増やす手段。ゴールの代替ではない |
| MCP として記憶を提供 | ❌ | 将来 backlog（§9 決定済み） | 将来の境界案。近場では書かない |
| 記録継続の計測（30日習慣） | △ capture events あり。習慣KPIは未定義 | 監査で継続率に言及 | 🎯 堀はデータ量ではなく習慣（§9 で指標候補として採用） |

---

## 5. 差分の整理（何がズレているか）

### 5.1 現状 vs 設計予定

設計どおり **「まず保存と検索（＋整理）」から入る** 順序は守れている。**QC-1〜QC-3 により「失わない入力」も揃った。**

残っている設計上の近場:

1. **実 transcription provider** — 境界は完成。差し替えのみ（`KIOKU_TRANSCRIPTION_PROVIDER`）
2. **PR-E** — Recall の SQL 前処理・短期キャッシュ・計測（受動経路の健全化）
3. **PR-G** — ChatGPT 取込 Phase A（embedding なし）→ Phase B（vector / insights）
4. 監査 F-3/F-4 — LIKE 線形劣化・表示ごとの走査
5. 一覧の cursor 化・一覧 Resource から raw 除外（監査推奨）

これらは「壊れない基盤」であって、**海馬の外付けの魔法**ではない。魔法の検証は §7 の第一段。

### 5.2 設計予定 vs やりたいこと

| やりたいこと | 設計での扱い | ズレ（改訂後） |
|---|---|---|
| 自動発火 | 第一段コンシェルジュ実験で今週から検証 | ✅ 着手トリガー確定。実装はまだ薄い（手動で足りる） |
| 領域またぎ | 第二段（件数蓄積後） | ✅ 「100件待ちで何もしない」は廃止。検証は第一段が先 |
| コンシェルジュ（週1手紙） | §7 で正式仕様化 | ✅ |
| raw 不変の徹底 | QC-1 で Model ガード実装済み | ✅ 解消 |
| 習慣＝堀 | 30日記録継続を指標候補として採用（§9） | △ 定義は決めた。計測ダッシュボードは未着手 |
| MCP 提供側 | 将来 backlog。近場ロードマップには載せない | ✅ |

### 5.3 現状 vs やりたいこと（一番痛い差）

```text
現状の価値提供:  「覚えさせる・探す・秘書の文脈に載せる・30秒で失わない」
やりたい価値提供:「忘れていたことを、システム側から差し込む」
```

前者は **QC 込みで実装済み**。後者はゼロ。入力摩擦の課題は下がったので、次は自動発火の価値検証（第一段）に集中する。

---

## 6. 外部視点から見た「設計に足りない問い」（要約）

ドキュメント化のため、会話上の3視点をギャップ項目に変換する。

| 視点 | 問い | 現状との関係 | ドキュメント上の扱い |
|---|---|---|---|
| シニア | 入力は続くか？ 夜23時に decision フィールドを埋める前提になっていないか？ | captured→enrich + QC（端末先行保存）で後段構造化は合格 | 入力摩擦を壊す UI を増やすな |
| シニア | 派生は全部作り直せるか？ | reenrich あり。一括再 enrich / モデル世代管理は無し | 「enrich 世代」を将来フィールド候補に |
| PM | 最初の魔法はいつ触れるか？ | 魔法＝自動発火は未実装 | **今週から**コンシェルジュ4週間実験（第一段） |
| PM | 堀はデータではなく習慣では？ | capture events あり。習慣KPIはこれから | 30日記録継続を指標候補に採用 |
| アーキテクト | 記憶ストア不変 / 知能は交換可能か？ | raw ガード実装済み。AI 周辺は Gateway 差し替え | 境界を明文化（下記§8） |
| アーキテクト | MCP で提供側に回るか？ | 無し | 将来 backlog（近場には書かない） |

スタック方針（会話結論）: Laravel & Vue は十分。乗り換えるな。足すなら AI レイヤー（評価・能動想起・将来 embedding/MCP）のみ。

---

## 7. 二段トリガー（§16 の置き換え・確定）

リポジトリ外設計の「記憶100件超で週次バッチ」を **単一トリガーとして待つことはしない。**  
代わりに **二段トリガー** を正とする。

| 段 | トリガー | 目的 | 実装の厚み | いつ |
|---|---|---|---|---|
| **第一段** | **今週から手動で開始**（件数条件なし） | 自動発火に価値があるかを知る | プロンプト＋手元操作。専用 UI / cron は作らない | **今〜4週間** |
| **第二段** | 第一段が価値あり **または** 記憶が十分に貯まった時点（目安100件超） | 領域またぎの本格パターン発見 | Job・集計・UI・評価 | 第一段の結果を見て昇格 |

並行で進めてよいもの: PR-E（受動経路の健全化）。ただし検証の主役にはしない。

### 7.1 コンシェルジュ4週間実験（正式仕様）

> 実装仕様の正: [kioku-final-remaining-implementation.md](../product/kioku-final-remaining-implementation.md) Phase B。
> 本節の「手元で選ぶ／手紙を書く」手順は、同仕様の手動 command（`kioku:letters:generate`）＋週1手紙 UI（シオリ/ナギ）へ置き換わった。
> 判定ラベル・成功条件・中止条件は本節のまま有効（cron・自動配信は引き続き範囲外）。

状態: **正式検証（第一段）**  
期間: **4週間**（開始は今週。カレンダー起算で連続4週）  
頻度: **週1回・手紙1通**  
実施者: プロダクトオーナー（個人開発前提の自分実験）

#### 手順（手動で足りる）

1. その週に貯まった記憶（`status=ready` 優先。sensitive は除外）を一覧から手元で選ぶ（件数の上限は運用で調整。最初は直近20〜50件目安）
2. AI に渡し、「忘れている気がする／今の自分に差し込む価値がありそうな」項目を手紙形式で1通生成する
3. 受け取った項目ごとに判定ラベルを付ける（下記）
4. 週次で1行メモを残す（成功件数・失敗理由・入力継続感）

専用画面・cron・自動配信は **この実験の範囲外**（[kioku-quick-capture.md](../product/kioku-quick-capture.md) の非目標と一致）。価値が確認できてから自動化を設計する。

#### 判定ラベル

| ラベル | 意味 |
|---|---|
| hit | 忘れていたが、今必要だった／差し込まれて助かった |
| soft-hit | 覚えていたが、再提示に意味があった |
| miss | 不要・的外れ・タイミング違い |
| sensitive-leak | 出したくない記憶が出た（即停止して除外条件を直す） |

#### 成功条件（4週間終了時）

- **hit が延べ4件以上**（週平均1件以上）、かつ
- **sensitive-leak が0件**、かつ
- 実験期間中に「記録する気が続いた」主観が維持されている（習慣KPIの定性面）

#### 中止・再設計条件

- sensitive-leak が1件でも出たら、表出除外を直すまで手紙生成を止める
- 4週終了時点で hit が1件以下なら、自動発火の価値仮説を見直す（領域またぎ第二段へ進まない）
- 「手紙を書く作業」自体が記録習慣を破壊したら中止（入力摩擦を増やさない原則）

#### 実験ログの置き場

- 判定結果はキオクへ `decision` または短文 manual として残してよい（スキーマ実証にもなる）
- プロンプト本文の正は、実験開始時にこのドキュメントまたは別メモへ1箇所に固定する（都度改変しない）

---

## 8. 境界の明文化（やりたいことを壊さないための線）

| 層 | 方針 | 今守れているか |
|---|---|---|
| **記憶ストア（中核）** | raw_content / 原音声不変、退屈な技術、user_id スコープ、再 enrich 可能 | ✅ QC-1 ガード実装済み |
| **知能（周辺）** | classify/extract/Recall/コンシェルジュ/transcription は Gateway 差し替え可能。モデル世代で全再処理できる前提 | ✅ 思想・境界とも合致（実 transcription provider のみ未接続） |
| **能動想起** | 「検索UIの延長」にしない。別ユースケース（手紙・通知・ヨユウ差し込み） | △ 第一段は手動手紙。自動化は未着手 |
| **やらない土俵** | 連想の速さ・無意識の整理・直感の再現で海馬と競争しない | ✅ ゴール定義で確定 |

---

## 9. 次アクション（決定済み）

初版 §9 の未決5件は、以下で **決定** する。実装バックログへの即時投入とは限らない。

| # | 問い | 決定 |
|---|---|---|
| 1 | コンシェルジュ実験を正式な次検証にするか | **する。** §7.1 の4週間手動実験を第一段トリガーとする。「100件を待つ」は第一段の条件にしない |
| 2 | 今日の decision 記憶をキオクへ実際に1件保存するか | **する。** スキーマ実証の完了儀式として、ゴール定義 decision を1件保存する |
| 3 | raw 不変を「更新APIを晒さない / policy で禁止」まで落とすか | **完了扱い。** QC-1 で Model updating ガードを実装済み。通常フローに raw 更新 API は晒さない。修復は `permitRawContentRepair()` のみ |
| 4 | 習慣KPI（例: 30日のうち記録した日数）を progress / 監査指標に足すか | **足す（候補として採用）。** 第一段実験と並行して定性＋簡易カウントで見る。ダッシュボード実装は必須にしない |
| 5 | MCP 提供は backlog に「将来」で残すか、今は書かないか | **将来 backlog に残し、近場ロードマップには書かない。** 第一段・第二段の邪魔をしない |

残作業（決定後のオペレーション）:

1. ゴール定義 decision をキオクへ1件保存する
2. §7.1 の手順で第一週の手紙を生成し、判定ラベルを付ける
3. 実 transcription provider は別チケット（境界は触らない差し替え）

---

## 10. 参照マップ（どこを読めばよいか）

| 知りたいこと | 正 |
|---|---|
| decision の JSON 形 | [seed-k-personal-os.md §6](../product/seed-k-personal-os.md) |
| クイックキャプチャ / 音声 / transcription 境界 | [kioku-quick-capture.md](../product/kioku-quick-capture.md) |
| 実装の現在地（SK2/3/5） | [progress.md](../progress.md) |
| Recall / ChatGPT / embedding の予定 | [ai-features-implementation-plan.md](./ai-features-implementation-plan.md) PR-E / PR-G |
| スケール・LIKE・事業制約 | [kioku-scalability-audit-and-business-model.md](../kioku-scalability-audit-and-business-model.md) |
| 型定義コード | `app/Domain/Kioku/Types/DecisionType.php` |
| Recall 実装 | `app/Domain/Kioku/Services/RecallService.php` |
| raw 不変ガード | `app/Domain/Kioku/Models/Memory.php`（updating） |
| capture / 冪等 | `app/Domain/Kioku/Services/CaptureMemoryService.php` |
| transcription 境界 | `app/Domain/Kioku/Transcription/TranscriptionGateway.php` |

---

## 変更履歴

| 日付 | 内容 |
|---|---|
| 2026-07-12 | 初版。現状 / 設計予定 / 海馬外付けゴールの差分を文書化 |
| 2026-07-14 | 改訂。QC-1〜QC-3・raw 不変・IndexedDB/冪等/音声 raw・transcription 境界を実装済みへ更新。二段トリガー確定。コンシェルジュ4週間実験を正式仕様化。§9 未決5件を決定済みに変更 |
