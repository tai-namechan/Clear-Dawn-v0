# 環境構成と環境差分の方針

## 原則

コードは一つ。環境（ローカル / ステージング / 本番）ごとに変えてよいのは `.env` で渡す**接続先と資格情報**だけとする。

- 変えてよい: DB・ストレージ・メールの接続先、API キー、ドメイン（`APP_URL`）、`APP_DEBUG`、ログレベル
- 変えてはいけない: バリデーション、認証・認可、パスワードポリシーなどの**セキュリティ上の制約**

`app()->isProduction()` / `app()->environment(...)` で挙動を分岐させるのは、次のような**事故防止・テスト専用の安全装置**に限る。

| 箇所 | 分岐 | 理由 |
|---|---|---|
| `AppServiceProvider::configureDefaults` | 本番のみ `migrate:fresh` などの破壊的コマンドを禁止 | ステージングはリセットできる必要がある |
| `CalendarProviderResolver::mockEnabled` | モックカレンダーは local / testing のみ | ステージング・本番で偽の予定を出さない |
| `StartKiokuConciergePilotCommand` / `TestKiokuLetterCommand` | 本番では `--confirm-production` 必須 | 誤実行防止 |

## 環境一覧

| | ローカル | ステージング | 本番 |
|---|---|---|---|
| ホスティング | 開発者の PC | 本番と同構成（Laravel Cloud） | Laravel Cloud |
| DB | MySQL 8.4（Docker, `compose.yaml`） | MySQL 8.4 | MySQL 8.4 |
| メール | `log`（`storage/logs/laravel.log` に出力） | 実送信 | 実送信（必須。新規登録のメール確認に使う。`docs/product/signup-policy.md`） |

テスト（`php artisan test`）は速度のため SQLite（インメモリ）で実行する。MySQL 固有の挙動に依存するクエリを書いた場合は、ローカルの MySQL でも動作を確認すること。

## パスワードポリシー

全環境で同じルールを適用する（`AppServiceProvider::configureDefaults` の `Password::defaults`）。

- 12 文字以上
- 英大文字と英小文字の両方を含む
- 数字を含む
- 記号を含む
- 漏洩済みパスワードでないこと（`uncompromised()`。Have I Been Pwned の range API にハッシュ先頭 5 文字だけを送って照合する）

テストでは外部 API に出ないよう、`Tests\TestCase` が `api.pwnedpasswords.com` を `Http::fake` で差し替える。ルール自体はテストでも同じものが適用される。

## ローカル環境のセットアップ

前提: PHP 8.4、Composer、Node.js、Docker Desktop。

```bash
cp .env.example .env
php artisan key:generate
docker compose up -d          # MySQL 8.4 を 127.0.0.1:3306 で起動
php artisan migrate --seed    # マスタデータのみ投入（ユーザーは作らない）
composer run dev              # Laravel + キュー + ログ + Vite
```

- MySQL Workbench からは `127.0.0.1:3306`、ユーザー / パスワードは `.env` の `DB_USERNAME` / `DB_PASSWORD` で接続する。
- アカウントは本番と同じく画面の新規登録から作る。シーダーで既知のパスワードを持つユーザーは作らない（シーダーを誤って本番で実行しても、ログインできるアカウントが生まれないようにするため）。
- 認証メール等のリンクは `storage/logs/laravel.log` に出力される。
- `npm run dev` 中も、CSS の `url('/images/...')` は Vite 開発サーバーが `public/` から配信する（`scripts/lib/vitePublicAssets.mjs`）。本番では Laravel が配信する。
