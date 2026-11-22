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
| オフライン表示 | 起動時に接続できないときはアプリ内のオフライン画面（`offline.html`）、利用中に切れたら画面上部に帯を表示 | 利用中の帯のみ |

## 構成

- `capacitor.config.ts`: アプリ ID `com.cleardawn.app`、表示名「Clear Dawn」、`server.url` は環境変数 `CAP_SERVER_URL`、`server.errorPath` は `offline.html`。
- `capacitor/www/`: アプリに同梱する最小限のファイル（起動画面・オフライン画面）。
- `ios/`: Xcode プロジェクト（Swift Package Manager）。カメラ・写真の利用目的（`NSCameraUsageDescription` など）を日本語で記載する。
- `resources/js/lib/native/`: ネイティブ機能の呼び出し口。`Capacitor.isNativePlatform()` が偽ならブラウザ用の動作に戻る。

## ビルドと配布（Mac なし）

- Codemagic（`codemagic.yaml`）のクラウド Mac でビルドし、App Store Connect API キーで署名して TestFlight へ送る。
- 必要な準備: Apple Developer Program 登録、App Store Connect でアプリ（Bundle ID `com.cleardawn.app`）作成、App Store Connect API キーを Codemagic に登録、`CAP_SERVER_URL` を Codemagic の環境変数に設定。

## 審査の前提（公開時）

- アプリ内でアカウント削除ができる（設定 > プロフィール、既存）。
- ログインに外部サービスを使っていないため「Apple でサインイン」は不要（Google はカレンダー連携のみ）。
- プライバシーポリシー URL と App Store のデータ収集申告（健康・食事データ）を用意する。
