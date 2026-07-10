# メモ仕様（凍結 — キオクへ移管）

対象フェーズ: Phase 1.5（**実装凍結**）  
状態: **キオクへ役割移管**（2026-07-10 確定。詳細は [seed-k-personal-os.md](../seed-k-personal-os.md)）

## 方針変更

Clear Dawn に独立した汎用メモ（`/memos`・`memos` テーブル）は**作らない**。

| 役割 | 正 |
|---|---|
| 汎用の思考・判断・学習の記録 | **キオク**（`memories`） |
| Clear Dawn 固有エンティティへの紐づけ | キオク memory を目標・ロードマップ等へ関連付ける |

例: ロードマップに対する考えを残す場合

1. キオクに memory を保存（`memory_type: thought` / `decision` 等）
2. Clear Dawn 側で `roadmap_id` 等へ参照を張る

これで二重管理を避け、キオクからも Clear Dawn からも同じ記録を見られる。

## 旧仕様（参考・実装しない）

以下は移管前のドラフト。実装の正としては扱わない。

- 画面: GET `/memos`
- テーブル: `memos`（id ULID, user_id, life_area_id, body, …）
- TOP Matrix セルからの「メモとして残す」導線

将来、セル項目からキオクへ送る導線が必要になった場合は、キオクのキャプチャ API / 送信コマンドとして再設計する。
