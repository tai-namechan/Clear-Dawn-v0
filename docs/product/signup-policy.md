# 登録ポリシー

Clear Dawn の新規アカウント登録に関する方針。**この文書が正である**
（`CLAUDE.md` / `AGENTS.md` のとおり、プロダクト仕様の正は `docs/`）。

## 現在の方針（2026-11-23 更新）

**新規登録は公開する。** iOS アプリ（`docs/product/ios-app.md`）とブラウザの両方から登録できる。

| 項目 | 方針 |
| --- | --- |
| UI からの登録導線 | **出す**（ランディングページ・ログイン画面の切り替えタブ） |
| 登録の手段 | メールアドレス + パスワード、または Apple でサインイン |
| パスワード | 全環境共通のポリシー（12文字以上・大小英字・数字・記号・漏えい済みでない。`docs/dev/environments.md`） |
| メール確認 | **必須**。未確認のユーザーは全ルートから確認画面へ送られる |
| Apple でサインイン | Apple は確認済みのメールアドレスにしか identity token を出さないため、作成時点で確認済みとして扱う |
| パスワード再設定 | **常に有効** |
| 登録のレート制限 | `register.store` に IP あたり 5回 / 時 |

`APP_PUBLIC_SIGNUP_ENABLED` は廃止した（PR #225）。登録の可否を環境変数で切り替える仕組みは持たない。

## なぜメール確認を必須にするか

登録を公開すると、誰でも捨てアドレスや他人のアドレスでアカウントを作れる。メール確認がないと、

- 他人のメールアドレスでアカウントを作れてしまう（なりすまし・通知の誤送信）
- AI 機能（1ユーザーあたり月 $10 のクォータ）をアカウントの量産で使い切られる

`User` は `MustVerifyEmail` を実装し、全ルートグループに `verified` ミドルウェアが付いている。
確認を通すまで `verification.notice` に送られ続ける（監査 C-1）。

## 本番で必要なこと

- **メール送信を実際に機能させる。** `MAIL_MAILER=log` のままだと確認メールが届かず、
  メールアドレスで登録したユーザーが誰も先に進めない（Apple でサインインのユーザーは影響を受けない）。
- 確認メールのリンクは端末の既定のブラウザで開く。ブラウザでログインしていなければ、
  ログインしてから確認が完了する。完了後はアプリに戻り、確認画面の「確認が済んだら開き直す」で先に進める。

## 既存ユーザー

メール確認を有効にする前に作られたアカウントは、マイグレーション
`2026_07_26_000001_backfill_email_verified_at_for_existing_users` で確認済みとして扱う
（確認メールを一度も受け取っていないため、締め出しを防ぐ）。

## 登録を止める判断をする場合

**`config/fortify.php` から `Features::registration()` を外してはならない。**
Wayfinder が `resources/js/routes/register` を生成しなくなり、それを静的 import している
`AuthRegisterForm.vue` / `AuthLoginForm.vue` / `AuthSimpleLayout.vue` が解決できず、フロントのビルドごと失敗する。
止める場合は `Features::registration()` を残したまま、`register` / `register.store` をミドルウェアで拒否する。

## 関連

- `docs/audit/2026-07-26-pre-release-audit.md` — C-1（メール検証）、C-2（登録フラグ。PR #225 で廃止）、C-2b（パスワード再設定の分離）
- `tests/Feature/Auth/SignupPolicyTest.php` — 本方針の回帰テスト
- `tests/Feature/Auth/EmailVerificationTest.php` — メール確認によるゲートの検証
