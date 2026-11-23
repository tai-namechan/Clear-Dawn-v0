# iOS アプリ（Capacitor）仕様

対象: Clear Dawn の iOS アプリ（TestFlight → App Store）。2026-11-23 決定。

## 方針

- 画面は Web 版（Laravel + Inertia + Vue）をそのまま使う。Swift での作り直しはしない。
- Capacitor の WebView が本番サーバー（`CAP_SERVER_URL`）を読み込む。Inertia はサーバーが画面を返すため、画面一式をアプリに同梱しない。
- iOS ならではの操作だけをネイティブ機能で補う（審査ガイドライン 4.2 対策も兼ねる）。
- Web（ブラウザ）で開いたときの動作は変えない。ネイティブ機能はアプリ内でだけ使い、ブラウザでは従来の入力手段に戻る。

## ネイティブ機能

| 機能 | アプリ内 | ブラウザ |
|---|---|---|
| 料理写真の撮影（食事の写真推定・成分表の撮影） | iOS のカメラ画面で撮影 | 従来のファイル選択 |
| バーコード読み取り（食品の JAN / QR） | iOS の読み取り画面（`@capacitor/barcode-scanner`） | 従来の Web カメラ読み取り |
| 触覚フィードバック | 記録の保存・ステップ完了・チェックで軽く振動 | なし |
| Apple でサインイン | iOS のネイティブ画面 | Apple の Web 画面 |
| オフライン表示 | 起動時に接続できないときはアプリ内のオフライン画面（`offline.html`）、利用中に切れたら画面上部に帯を表示 | 利用中の帯のみ |

## ログイン・新規登録（2026-11-23 追加）

- アプリで開いたとき（User-Agent に `ClearDawnApp`）は、公開ランディングページを出さず、未ログインならログイン画面へ直接送る。
- ログイン・新規登録の画面はアプリ向けのデザイン（和紙の背景、ロゴ、ログイン / 新規登録の切り替えタブ、大きな入力欄とボタン）。ブラウザでも同じ画面を使う。
- 新規登録は公開（`APP_PUBLIC_SIGNUP_ENABLED` は廃止済み、PR #225）。パスワードは全環境共通のポリシー。メールアドレスで登録した場合はメール確認が必須（`docs/product/signup-policy.md`）。確認画面に「確認が済んだら開く」を置き、ブラウザで確認したあとアプリに戻れるようにする。
- **Apple でサインイン**:
  - アプリ内: iOS のネイティブ画面（`@capacitor-community/apple-sign-in`）。サーバーが発行した使い捨ての nonce を渡し、返ってきた identity token をサーバーで検証する（署名・発行者・対象・nonce）。
  - ブラウザ: Apple の Web 画面へリダイレクト（Services ID、`form_post` のコールバック。CSRF の代わりに暗号化した nonce Cookie で検証）。
  - `users.apple_id`（Apple の `sub`）でアカウントを特定する。初回は新規作成（名前は Apple から受け取れた場合のみ、なければ「Clear Dawn ユーザー」）。パスワードは推測できない乱数を設定する。
  - Apple のメールアドレスが既存アカウントと同じでも**自動では連携しない**（なりすまし防止）。その場合はメールアドレスでログインするよう案内する。
  - Apple の設定（Services ID・Key・Team ID）が未設定の環境ではボタンを出さない。
- App Store 公開前の宿題: アカウント削除時の Apple トークン失効（ガイドライン 5.1.1(v)）。

## 構成

- `capacitor.config.ts`: アプリ ID `jp.seedk.cleardawn`、表示名「Clear Dawn」、`server.url` は環境変数 `CAP_SERVER_URL`、`server.errorPath` は `offline.html`。Bundle ID は保有ドメイン `seedk.jp` に揃える（Clear Pitch の `jp.seedk.clearpitch` と同じ系列。初回アップロード後は変更不可）。
- `capacitor/www/`: アプリに同梱する最小限のファイル（起動画面・オフライン画面）。
- `ios/`: Xcode プロジェクト（Swift Package Manager）。カメラ・写真の利用目的（`NSCameraUsageDescription` など）を日本語で記載する。
- `resources/js/lib/native/`: ネイティブ機能の呼び出し口。`Capacitor.isNativePlatform()` が偽ならブラウザ用の動作に戻る。

## ビルドと配布（Mac なし）

- Codemagic（`codemagic.yaml`）のクラウド Mac でビルドし、App Store Connect API キーで署名して TestFlight へ送る。
- 必要な準備: Apple Developer Program 登録、App Store Connect でアプリ（Bundle ID `jp.seedk.cleardawn`）作成、App Store Connect API キーを Codemagic に登録、`CAP_SERVER_URL` を Codemagic の環境変数に設定。
- Apple でサインインの準備（Apple Developer > Certificates, Identifiers & Profiles）:
  - App ID `jp.seedk.cleardawn` の Capabilities で「Sign in with Apple」を有効にする（`ios/App/App/App.entitlements` と一致させる）。
  - Web 用に Services ID（例 `jp.seedk.cleardawn.web`）を作り、ドメインと Return URL `https://<本番>/auth/apple/callback` を登録する。
  - Keys で「Sign in with Apple」のキー（.p8）を作る。
  - 本番の環境変数: `APPLE_CLIENT_ID`（Services ID）、`APPLE_TEAM_ID`、`APPLE_KEY_ID`、`APPLE_PRIVATE_KEY`（.p8 の内容）、`APPLE_REDIRECT_URI`、`APPLE_BUNDLE_ID`。

## 審査の前提（公開時）

- アプリ内でアカウント削除ができる（設定 > プロフィール、既存）。
- 「Apple でサインイン」を提供済み（他社ログインは無いが、iOS で使いやすくするため）。
- プライバシーポリシー URL と App Store のデータ収集申告（健康・食事データ）を用意する。
