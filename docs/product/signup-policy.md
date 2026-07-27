# 登録ポリシー

Clear Dawn の新規アカウント登録に関する方針。**この文書が正である**
（`CLAUDE.md` / `AGENTS.md` のとおり、プロダクト仕様の正は `docs/`）。

## 現在の方針

**当面は限定利用。** 一般公開は行わない。

| 項目 | 方針 |
| --- | --- |
| UI からの登録導線 | **出さない**（ランディングページ・ログイン画面ともに非表示） |
| `/register` への URL 直打ち | **現時点では許容する**（ルートは塞がない） |
| パスワード再設定 | **常に有効**（登録導線の有無とは無関係） |
| メール確認 | **必須**（未検証ユーザーは全ルートから弾かれる） |

制御は `APP_PUBLIC_SIGNUP_ENABLED`（`config/app.php` の `public_signup_enabled`、既定 `false`）。

## なぜ URL 直打ちを塞いでいないか

塞ぐほどのリスクが無いと判断しているため。理由は2つある。

1. **URL を知らなければ到達しない。** 導線が無い以上、偶然たどり着く経路は無い。
2. **登録できてもアプリの中身には到達できない。** `User` は `MustVerifyEmail` を実装しており、
   全ルートグループに `verified` ミドルウェアが付いている。メール確認を通すまで
   `verification.notice` にリダイレクトされ続ける。

つまり実質的な防御はメール確認が担っている。「登録できること」と
「アプリを使えること」は分かれている。

## 塞ぐ判断をする場合

**`config/fortify.php` から `Features::registration()` を外してはならない。**

Wayfinder は登録済みルートから TypeScript モジュールを生成する。ルートを消すと
`resources/js/routes/register` が生成されなくなり、それを静的 import している

- `resources/js/components/auth/AuthRegisterForm.vue`
- `resources/js/components/auth/AuthLoginForm.vue`

が解決できず、**フロントエンドのビルドごと失敗する**。
実行時フラグでビルド生成物の形が変わる状態を作らないこと。

塞ぐ場合は `Features::registration()` を残したまま、`register` / `register.store`
に対してミドルウェアで拒否する。あわせて
`tests/Feature/Auth/SignupPolicyTest.php` の
`test_direct_url_registration_is_intentionally_still_reachable` を
「拒否されること」の検証に書き換える。

## 方針を変える場合に必要な作業

一般公開へ切り替えるときは以下をセットで行う。

- `APP_PUBLIC_SIGNUP_ENABLED=true` を本番環境変数に設定（config キャッシュの再生成が必要）
- **メール送信が実際に機能していることの確認。** `MAIL_MAILER=log` のままだと
  確認メールがログに出るだけで届かず、新規ユーザーが誰も登録を完了できない
- AI 利用クォータ（1ユーザーあたり月 $10）の想定ユーザー数に対する見直し
- 登録レートリミット（現在 `throttle:5/hour/IP`）の妥当性の再検討

## 関連

- `docs/audit/2026-07-26-pre-release-audit.md` — C-1（メール検証）、C-2（本方針により取り下げ）、C-2b（パスワード再設定の分離）
- `tests/Feature/Auth/SignupPolicyTest.php` — 本方針の回帰テスト
- `tests/Feature/Auth/EmailVerificationTest.php` — メール確認によるゲートの検証
