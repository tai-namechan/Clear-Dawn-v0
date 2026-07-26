# チームワークスペース（ローカルプロトタイプ）

チーム画面は個人版と別の `team` guardを使い、`team.localhost` で提供します。ローカル専用デモログインは `APP_ENV=local` の場合だけルートが登録され、本番・ステージングには存在しません。

## セットアップ

PHP 8.4、Composer、Node.jsを利用できる環境で、通常のセットアップ後に次を実行します。

```bash
php artisan migrate
php artisan db:seed --class=TeamWorkspaceDemoSeeder
npm install
npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

`.env` は次を設定します。Googleログインを確認する場合だけ、専用OAuthクライアントの秘密値を追加してください。Calendar連携用クライアントは流用しません。

```dotenv
TEAM_APP_DOMAIN=team.localhost
TEAM_APP_URL=http://team.localhost:8000
GOOGLE_TEAM_AUTH_ENABLED=false
GOOGLE_TEAM_AUTH_CLIENT_ID=
GOOGLE_TEAM_AUTH_CLIENT_SECRET=
GOOGLE_TEAM_AUTH_REDIRECT_URI=http://team.localhost:8000/auth/google/callback
SESSION_DOMAIN=null
```

## 確認方法

`http://team.localhost:8000/login` を開き、「ローカルデモで確認」を選びます。デモスタッフは `coach@team.local`、チームは「Clear Dawn ジュニアーズ」です。

- `http://team.localhost:8000/`
- `http://team.localhost:8000/t/clear-dawn-juniors/dashboard`
- `http://team.localhost:8000/t/clear-dawn-juniors/athletes`

Windows / WSL環境等で `team.localhost` が解決しない場合は、hostsファイルへ `127.0.0.1 team.localhost` を追加します。

## プロトタイプの共有境界

保護者同意と共有ポリシーは後続Phaseです。現時点では、所属選手の記録日数、練習回数、センシティブな内容を含まない状態要約だけを返します。食品名・量、毎日の体重、食事写真、症状メモ、Kiokuはチーム画面へ返しません。
