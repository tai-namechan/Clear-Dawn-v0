# Seed K Personal OS — 確定設計（切替・境界）

状態: **確定**（2026-07-10）  
関連: 添付の Kioku System 設計書 / ヨユウ・キオク機能仕様書（リポジトリ外）。本ドキュメントが Clear Dawn リポジトリ側の確定事項の正。

## 1. プロダクト構成

同一 Laravel アプリ・同一認証・同一 DB 上の 3 ワークスペース。

| キー | 表示名 | URL | 役割（tagline） | UIテーマ |
|---|---|---|---|---|
| `clear_dawn` | Clear Dawn | `/dashboard` ほか既存 | 思考の整理・人生の方針 | 既存 `--cd-*` |
| `yoyu` | ヨユウ | `/yoyu` | 焦らず、前へ回すAI秘書 | ライトティール `#129488` 系 |
| `kioku` | キオク | `/kioku` | 記憶の保存・検索・想起 | 書庫トーン（和紙×墨×藍インク `#3E5688`） |

- ユーザー向け表示は「キオク」（「キオクConsole」は内部名。画面・切替・通知に出さない）
- 事業上の主役は当面 Clear Dawn とヨユウ。キオクは基盤 + 最小の直接操作画面

## 2. プロダクト切替 UI（確定）

- ヘッダーに**小さな現在プロダクト表示**（トリガー）
- クリックで**3カード大型モーダル**を開く
- モーダル内: 役割（tagline）・プレビュー・現在利用中
- 選択後は各プロダクト専用レイアウトへ遷移
- プレビューは将来、各プロダクトの実データ縮小コンポーネントにする。MVP は固定プレビューでよい

## 3. 実装順（確定）

1. ProductSwitcher + `/yoyu` `/kioku` プレースホルダ（薄い PR1）
2. キオク P0（保存・一覧・検索）
3. キオク P1（Queue AI 整理・スキーマレジストリ）
4. ヨユウ Today
5. ヨユウ秘書 × Kioku Recall

補足:

- PR1 は現行 Clear Dawn を止めない。切替シェルは薄く作る
- `yoyu_focus_items` はヨユウのデータ設計確定後に作る（キオク実装中に先回りしない）
- キオク P0 では `RecallService` のインターフェース用意まででよい。価値検証の中心は保存・検索・エラー再利用

## 4. 主キー（確定）

ADR-0001 に従う。

- 新規ドメインテーブル PK: ULID（`memories` / `memory_links` / `ai_usage_logs` / `connectors` 等）
- `user_id`: bigint（`users.id` に合わせる）

## 5. Clear Dawn「メモ」の扱い（確定）

- 汎用メモは**キオクが正**。Clear Dawn に独立した `memos` テーブル・汎用メモ画面は作らない
- roadmap の「メモ」は削除ではなく、キオクへ役割移管する前提で**凍結**
- Clear Dawn 固有の文脈（目標・ロードマップ等）へ紐づける場合は、キオクの memory を参照する（二重管理しない）

例:

- Kioku memory（`thought` / `decision` 等）
- → Clear Dawn の `roadmap_id` 等へ関連付け

## 6. decision の structured_data（確定）

```json
{
  "situation": "現在の状況",
  "constraints": ["考慮した制約"],
  "options": ["選択肢A", "選択肢B"],
  "decision": "最終的に決めたこと",
  "reason": "その理由",
  "review_condition": "いつ・何が起きたら見直すか"
}
```

`review_condition` は未来の再判断のための必須フィールドとする。

## 7. 認証・ルート受入

- Given 未認証ユーザー
- When `/yoyu` または `/kioku` にアクセス
- Then `login` へ redirect（401 ではない）

## 8. ディレクトリ方針（確定）

- 新規プロダクト領域は `app/Domain/{Kioku,Yoyu,Shared}` に置く
- Clear Dawn 既存の Controllers / Queries / Services は移動しない
- Http Controllers は現行どおり `app/Http/Controllers/{Kioku,Yoyu}` に置いてよい
- 共通 UI プリミティブ（`components/ui/*`）の内部改変はしない
- 切替は `components/os/ProductSwitcher.vue` 等の新規共通部品で追加する
