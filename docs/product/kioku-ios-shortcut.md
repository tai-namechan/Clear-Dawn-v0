# iOS Shortcut からキオクへ残す

Feature flag: `KIOKU_IOS_SHORTCUT_ENABLED=false`（既定 OFF）

## 1. トークン発行

1. キオク → 設定
2. 「Capture Token を発行」
3. 表示された平文トークンを一度だけコピー（再表示されない）
4. 失効は同画面の Revoke

トークンは DB に hash のみ保存。scope は `kioku:capture` 固定。

## 2. Shortcut 手順（再現可能）

1. ショートカット App で新規作成「キオクへ残す」
2. 「共有シート」受け取り（テキスト / URL / ファイル）
3. 「URL の内容を取得」:
   - Method: POST
   - URL: `https://<your-domain>/api/kioku/captures`
   - Headers:
     - `Authorization: Bearer <token>`
     - `Idempotency-Key: <UUID>`
     - `Accept: application/json`
   - Body (JSON for text):
     - `kind=text`
     - `text=<共有テキスト>`
   - Body (multipart for audio): `audio=<ファイル>`
4. 応答の `message` が「原情報を保存しました」なら完了（AI整理待ち）

## 3. 注意

- トークンを URL query に付けない
- ログに平文トークン・raw を出さない
- 失敗時は Idempotency-Key を再利用して再送してよい
