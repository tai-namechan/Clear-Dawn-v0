# キオク コンシェルジュ — 14日日次pilot → 週次定常

- 作成日: 2026-07-14
- 状態: **実装仕様（PR #128 追補）**
- 正本の位置づけ: Phase B（[kioku-final-remaining-implementation.md](./kioku-final-remaining-implementation.md)）の週次手紙を土台に、配信安全・テスト導線・14日日次pilotを追加する
- 「送る」の定義: **Kioku Home / Letter Detail へのアプリ内表示**。メール / push / Slack / 新通知チャネルは作らない

---

## 1. 実験 cadence

| 期間 | cadence | 運用 |
|---|---|---|
| 2026-07-15 〜 2026-07-28（両端含む14日） | `daily` pilot | ユーザーtimezone上で1日最大1通・最大2項目。schedulerで配信 |
| 2026-07-29 以降 | `weekly` steady-state | daily schedule は `completed`。週次は PR #128 の既存手動 command を維持（週次自動cronは今回追加しない） |

日付をアプリコードへ直書きしない。`kioku:letters:pilot:start` の引数（`--start` / `--days`）として DB の `kioku_concierge_schedules` に記録する。

### 1.1 daily pilot の不変条件

- 過去日の自動 backfill をしない（scheduler が落ちていた日をまとめて送らない）
- 14日目処理後に schedule を `completed` にし、15日目を生成しない
- 日次は最大 **2項目**。0件でも正常
- 週次は既存どおり最大 **5項目**
- live と test、daily と weekly の評価指標を混ぜない

### 1.2 ノイズ抑制

- 直近3通の live daily が連続 unread（公開から24時間未開封）なら schedule を `paused` にし、以降 AI を呼ばない
- `sensitive_leak` は即時 halt（Letter + schedule + 元 Memory 隔離）
- リアルタイム通知化しない

---

## 2. データ分離

| 区分 | `mode` | `cadence` | 実験分母 / cooldown / 参照更新 |
|---|---|---|---|
| 本番日次 | `live` | `daily` | 対象（`kioku_concierge_daily_pilot_v1`） |
| 本番週次 | `live` | `weekly` | 対象（`kioku_concierge_v1`、週次別集計） |
| 実AIテスト便り | `test` | daily/weekly いずれでも枠非消費 | **対象外**（参照回数 / `last_referenced_at` / `last_delivered_at` / cooldown / 評価Memoryを汚さない） |
| fixture preview | （DBなし） | — | 対象外。AI call 0 |

例外: test 中にユーザーが `sensitive_leak` を付けた場合だけ、元 Memory の quarantine と halt を live 同様に実行する。

---

## 3. schema 要点

詳細は [tables.md](../data/tables.md)。

### 3.1 `kioku_letters`（追補列）

- `mode`: `live` / `test`
- `cadence`: `daily` / `weekly`
- `delivery_date`: ユーザーtimezone上の配信対象日
- `dedupe_key`: live 冪等キー（`daily:YYYY-MM-DD` / `weekly:YYYY-MM-DD`）。test は `test:{ulid}` で live 枠を消費しない
- unique: `(user_id, dedupe_key)`（旧 `(user_id, week_start)` は置き換え）
- `week_start`: 後方互換・集計用。daily でも `delivery_date` の週開始日を保存
- `pilot_day`: nullable 1〜14
- `test_expires_at` / `halted_at` / `halt_resolved_at` / `halt_resolution_note`
- `retry_count` + `generation_meta.failures[]` で失敗履歴を append

### 3.2 `memories.last_delivered_at`

- live letter を published/empty 確定する同一 transaction 内で、採用 Memory の `last_delivered_at` を更新
- 候補条件は `last_referenced_at` と `last_delivered_at` の両方を考慮（いずれかが14日以内なら候補外）
- test letter は更新しない

### 3.3 `kioku_concierge_schedules`

ユーザーごとの pilot 状態。対象ユーザーを env へ直書きしない。

`state`: `inactive` → `active` → (`paused` | `halted`) → `completed`

`next_delivery_at` 不変条件:

- DB 保存値は常に UTC
- pilot 期間内で `state=active` なら必ず非 NULL
- `halted` / `paused` / `completed` なら NULL
- 復帰時（`pilot:resume` / `resolve-halt`）は過去日を backfill せず、現地の「本日配信時刻前→本日 / 過ぎた→翌日」を `computeNextDeliveryAt()` で計算
- 次回日時が `pilot_end_date` を超える場合は `completed` + `next_delivery_at=NULL`

`pilot:start` も同じ UTC 変換経路を使い、固定オフセット（例: 9時間引き）は使わない。

---

## 4. sensitive halt（本当の停止）

`sensitive_leak` 保存時（単一 transaction）:

1. Letter を `lockForUpdate`
2. LetterItem を `lockForUpdate`
3. verdict 保存
4. 参照 Memory（所有者本人）を `sensitive=true`
5. Letter を `status=halted`、`halted_at=now()`
6. 対応 schedule があれば `state=halted`
7. 評価 Memory に手紙本文/item が含まれる場合はそれも `sensitive=true`

生成前ガード: 未解決 halt（`status=halted` かつ `halt_resolved_at IS NULL`）がある所有者への live / 実AI test 生成は拒否。AI call 0、Letter 新規行 0。`--force` bypass なし。別ユーザーには影響しない。

解消:

```bash
php artisan kioku:letters:resolve-halt {userId} {letterId} --note="確認内容"
```

解消後、未解決 halt が残っていなければ schedule を `pilot:resume` と同じ経路で再起動する（`state=active` + 次回 `next_delivery_at` UTC）。期限切れ pilot は `completed`。誤判定でも自動で `sensitive=false` に戻さない。

---

## 5. テスト導線

### 5.1 fixture preview（AI/DBコストなし）

```text
GET /kioku/letters/preview?character=shiori&case=five
GET /kioku/letters/preview?character=nagi&case=one
GET /kioku/letters/preview?character=shiori&case=empty
```

auth + verified。`[プレビュー]` 表示。verdict は保存しない。

### 5.2 実データ + AI の test letter

```bash
php artisan kioku:letters:test {userId} --character=shiori --context="任意"
```

- `KIOKU_CONCIERGE_TEST_ENABLED=false` が default
- production では `--confirm-production` 必須
- Home では通常枠と分離した `[テスト便り]` 表示

---

## 6. pilot コマンド

```bash
php artisan kioku:letters:pilot:start {userId} \
  --start=2026-07-15 --days=14 --time=21:00 --timezone=Asia/Tokyo \
  --send-now --confirm-production --dry-run

php artisan kioku:letters:pilot:status {userId}
php artisan kioku:letters:pilot:pause {userId} --note="理由"
php artisan kioku:letters:pilot:resume {userId} --note="確認内容"
php artisan kioku:letters:pilot:report {userId}
```

scheduler: 毎分 dispatcher が due schedule を調べ、user ごとに unique Job を dispatch。`withoutOverlapping` + `onOneServer`。過去日 backfill なし。

---

## 7. 評価と success / kill

live daily の評価 Memory `structured_data`:

- `experiment = kioku_concierge_daily_pilot_v1`
- `mode`, `cadence`, `delivery_date`, `pilot_day`, `item_count`, verdict counts
- `hit_rate` / `useful_rate`（empty は null。0除算や0%偽装禁止）
- `opened_within_24h`, `consecutive_unopened`, `character`, `empty`

`pilot:report` 表示:

- generated日数 / 14
- 24時間以内開封数 / generated
- HIT率（目標25%以上）/ useful率（HIT+soft_hit、目標50%以上）— 母数0なら `N/A`
- 連続未読最大数、empty日数、sensitive_leak件数（成功条件0）
- pilot前14日とpilot中の Memory 記録日数/件数
- paused/halted/completed と理由

kill:

- sensitive_leak 1件 → 即 halt
- 連続未読3通 → pause
- 人間が手紙を開くこと自体を避ける状態になったら実験失敗として記録

---

## 8. UI 表示

| 種別 | 表示 |
|---|---|
| daily live | `2026/7/15のキオク便り` |
| weekly live | 既存の週表示（`M/Dの週`） |
| test | `[テスト便り]` badge、通常枠と分離 |
| fixture | `[プレビュー]` badge |

Home 通常枠には最新の live 便だけ。empty は詳細または「確認した」へ到達可能。
