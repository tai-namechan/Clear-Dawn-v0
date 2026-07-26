# チームワークスペース（ローカルプロトタイプ）

チーム画面は個人版と別の `team` guardを使い、`team.localhost` で提供します。ローカル専用デモログインは `APP_ENV=local` の場合だけルートが登録され、本番・ステージングには存在しません。

本ドキュメントは関係者が業務フローをレビューできる「操作可能な読み取り専用プロトタイプ」の設計正です。更新・承認・同意フローは対象外です。

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
- `http://team.localhost:8000/t/clear-dawn-juniors/programs`
- `http://team.localhost:8000/t/clear-dawn-juniors/reports`
- `http://team.localhost:8000/t/clear-dawn-juniors/settings`

Windows / WSL環境等で `team.localhost` が解決しない場合は、hostsファイルへ `127.0.0.1 team.localhost` を追加します。

---

## プロトタイプ詳細設計（読み取り専用レビュー版）

### 対象利用者

| 利用者 | アクセス |
|---|---|
| チームスタッフ（owner / admin / head_coach / coach / nutrition_staff / conditioning_staff）かつ active 所属 | 可 |
| athlete ロールの membership | 管理画面へ不可（404） |
| inactive / suspended membership | 不可（404） |
| inactive team | 不可（404） |
| 他チームのスタッフ | 当該チームへ不可（404） |
| 個人版 `web` ガードのユーザー | team 画面へ不可 |

### 画面遷移

```
/login
  → / （所属1件なら dashboard、複数なら Select）
  → /t/{team}/dashboard
  → /t/{team}/athletes
      → /t/{team}/athletes/{athlete}                 # 概要
      → /t/{team}/athletes/{athlete}/training
      → /t/{team}/athletes/{athlete}/meals
      → /t/{team}/athletes/{athlete}/condition
      → /t/{team}/athletes/{athlete}/goals
      → /t/{team}/athletes/{athlete}/report
  → /t/{team}/programs
  → /t/{team}/reports
  → /t/{team}/settings
```

選手詳細タブは **URL パスで切り替える**（ブラウザの戻る・進むで状態が復元される）。Inertia の `Link` で遷移し、クライアントだけのタブ状態には依存しない。

### 権限（全 Team 画面共通）

1. `team` guard で認証済み
2. `TeamDataAccessPolicy::accessTeam` — active team + active staff membership + URL の team 一致
3. 選手詳細系は追加で `viewAthlete` — 対象選手が当該 team に active athlete 所属
4. 他 team の ID / slug 指定は 404

athlete ロールは `accessTeam` を通過できないため、管理画面全般へ入れない。

---

### A. 選手詳細

#### 目的

所属選手の記録状況を、安全な集計だけで確認し、現場の週次レビュー業務を想像できるようにする。

#### 表示項目

| タブ | 表示 | 禁止 |
|---|---|---|
| 概要 | 7日トレーニング回数、食事記録日数、安全なコンディション要約、目標進捗（達成/進行中件数）、最終記録日時 | email、体重値、食品名、症状本文、Kioku |
| トレーニング | 日付、種目/カテゴリ（RoutinePlan.title または「練習」）、実施時間（分）、強度帯（session_rpe を段階化）、完了状態、短い安全要約（完了/中断のみ。note は出さない） | session note、個人メモ |
| 食事 | 日別の記録有無、食事回数、記録継続状況（連続日数）、安全指標（記録日数・平均食事回数）。食品名・量・PFC・画像・自由記述は出さない | name, quantity, kcal, PFC, note, 写真 |
| コンディション | 日付、回復/疲労の段階ラベル、トレーニング可否の安全要約（記録有無ベース）。DailyCheckin の数値は段階へ丸める | note、症状自由記述、医療判断文言、region_tension 詳細 |
| 目標 | 目標名、期間（deadline）、進捗率または status 表示。`why` / metric note / 生の体重系 metric 値は出さない | why、個人メモ、生の体重値 |
| 個人レポート | 直近7日/30日の集計、トレーニング実施傾向、食事記録継続傾向、コンディション傾向、「診断ではない」注記 | 診断表現、禁止データの再掲 |

#### 空状態

各タブは対象期間にデータが0件のとき、「対象期間に記録はありません」を表示する。

#### 集計期間・タイムゾーン

- 基準タイムゾーンは `teams.timezone`（デモは `Asia/Tokyo`）
- 「今日」および「直近 N 日」はチーム TZ のカレンダー日で計算する
- 7日窓: チーム今日を含む過去7カレンダー日
- 30日窓: チーム今日を含む過去30カレンダー日

---

### B. プログラム（チーム向け・読み取り専用）

#### 目的

チームが選手へ割り当てる練習プログラムの一覧イメージをレビューする。

#### 表示項目

- タイトル
- 対象期間（starts_on / ends_on）
- 対象人数（assignments 数）
- 公開状態（draft / published / archived）
- 練習項目の概要（タイトル一覧、最大数件）
- 選手への割り当て状況（割り当て済み人数 / 在籍選手数）
- 空状態: 「公開中のプログラムはありません」

#### データ設計（仮定・プロトタイプ専用）

個人版 `programs` は user 所有の本番モデルのため再利用しない。衝突を避けるため、チーム専用の最小テーブルを追加する。

| テーブル | 用途 |
|---|---|
| `team_programs` | チーム向けプログラム本体 |
| `team_program_items` | 練習項目の概要（タイトル・並び） |
| `team_program_assignments` | 選手（users.id）への割り当て |

本番の Program エンジン統合・承認・版管理は持ち越し。

---

### C. レポート（チーム全体）

#### 目的

チーム全体の記録状況を7日/30日で俯瞰する。

#### 表示項目

- 期間切替（`?period=7|30`、デフォルト7）
- 在籍人数
- トレーニング実施数
- 食事記録継続状況（記録日数合計、要確認人数）
- 記録確認が必要な人数（期間内食事記録日が閾値未満。7日窓は3日未満、30日窓は10日未満）
- 選手別の安全な集計表（名前、練習回数、食事記録日、コンディション記録日、要確認フラグ）
- 「健康状態の診断ではない」旨を明記

#### 禁止

体重値、食品詳細、症状本文、Kioku、メール。

---

### D. チーム設定

#### 目的

チーム基本情報とスタッフ体制を確認する（編集はしない）。

#### 表示項目

- チーム基本情報（name, organization_type, timezone, status）
- スタッフメンバー一覧（name, role, status, joined_at）
- 招待中メンバー（email のローカル部マスクまたは invitee 表示名、role, expires_at）。業務上不要な完全メールの露出を避けるため、プロトタイプでは `local***@domain` 形式でマスクする
- 編集ボタンは置かず、「プロトタイプでは編集対象外」と明示

選手本人・個人版ユーザーへ管理情報を公開しない（team guard + staff 所属必須）。

---

### 共有可能データ / 表示禁止データ

#### 共有可能（安全な集計）

- 記録の有無・回数・日数
- 完了状態（completed / aborted / in_progress）
- RPE や check-in 指標を段階ラベルへ丸めた値
- 目標名と status / 進捗率（公開可能とみなすもののみ）
- チームプログラムのタイトル・期間・公開状態・割り当て人数

#### 表示禁止

- 毎日の体重値
- 食品名と摂取量、PFC、食事写真、食事自由記述
- 症状の自由記述、DailyCheckin.note、SymptomObservation.note
- Kioku / memory 本文
- 個人版の非共有メモ（RoutineSession.note 等）
- メール等、業務上不要な個人情報（設定画面の招待メールはマスク）
- 医療診断と誤認される評価文言

共有可能な安全な集計と、共有禁止の生データは **Query / Resource 層で分離**する。Controller に生モデルをそのまま渡さない。

---

### プロトタイプ対象範囲

含まれるもの:

- 読み取り専用の画面遷移と集計表示
- デモ Seeder による架空データ
- 所属境界・共有境界の自動テスト
- PC / モバイルのナビ（準備中ラベル撤去）

含まれないもの（本番へ持ち越し）:

- 登録、編集、削除
- 承認フロー
- 保護者・選手の共有同意
- 通知
- 監査ログ
- データ保持期間
- PDF / CSV 出力
- 本番向け権限マトリクス
- 医療・栄養判断に関する正式な監修
- 個人版 Program エンジンとの統合

---

### 実装方針（仮定）

1. 選手のトレーニング / 食事 / コンディション / 目標は既存個人版テーブルを読み取り専用で集計する
2. チームプログラムだけは専用テーブルを新設する（個人版 `programs` と衝突させない）
3. 集計ロジックは `app/Queries` に置き、Controller は認可と Inertia 返却に徹する
4. Inertia props は配列 Resource 相当の安全な shape のみ
5. Seeder は `updateOrCreate` / `firstOrCreate` で再実行耐性を持たせる
6. デモデータは完全な架空人物のみ

Laravel Boost の `search-docs` MCP が当該環境で未提供のため、インストール済みパッケージ版（Inertia v3 / Laravel 13）と既存兄弟実装を正として進める。

---

## 関係者レビュー手順

1. ログイン画面で、個人版とは分離されたチーム専用導線と注意書きを確認する
2. 「ローカルデモで確認」から入り、Dashboard の4カードが概ね `3名 / 練習数件 / 13日 / 要確認あり` になることを確認する
3. 選手一覧で氏名検索、7日間の練習回数・食事記録日数、最終記録を確認する
4. 選手詳細を開き、概要 / トレーニング / 食事 / コンディション / 目標 / レポートの各タブを URL 付きで切り替えて確認する
5. 各タブに食品名・体重値・症状本文が出ないことを確認する
6. プログラム一覧でタイトル・期間・公開状態・割り当てを確認する（空状態も確認）
7. レポートで7日/30日切替と選手別集計、診断ではない注記を確認する
8. チーム設定で基本情報・スタッフ・招待中を確認し、編集操作がないことを確認する
9. 他チーム URL を直接開くと拒否されることを確認する
10. PC幅とモバイル幅でサイドバーと表の横スクロールを確認する
11. ログアウト後、チーム画面へ戻るとログイン画面へ誘導されることを確認する

Google OAuthはコードと自動テストで招待・所属必須を保証していますが、実Googleアカウントでの確認には専用OAuthクライアント設定が必要です。ローカルレビューではデモログインを使用してください。

## プロトタイプの共有境界

保護者同意と共有ポリシーは後続Phaseです。現時点では、所属選手の記録日数、練習回数、センシティブな内容を含まない状態要約だけを返します。食品名・量、毎日の体重、食事写真、症状メモ、Kiokuはチーム画面へ返しません。
