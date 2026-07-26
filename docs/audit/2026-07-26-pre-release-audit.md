# 本番リリース前 最終監査レポート（2026-07-26）

対象コミット: `fe34f58`（`main`）
対象範囲: リポジトリ全体（Laravel / Vue / Inertia / DB / Queue / Job / API / 認証 / 権限 / Storage / Cloud 構成 / テスト / CI / 設定）

---

## 0. この文書の読み方

各指摘は次の5点セットで記述する。**「直った/直っていない」ではなく「何が壊れていて、直すと何が良くなるか」を残すこと**がこの文書の目的である。

| 見出し | 意味 |
| --- | --- |
| 現象 | 放置した場合、本番で実際に何が起きるか |
| 根本原因 | どのコードの、どの判断が原因か（file:line） |
| あるべき姿 | 設計上、本来どうなっているべきか |
| 修正で得られるもの | 直すことで何が改善するか（守れるようになるもの） |
| 検証方法 | 修正が効いていることをどう機械的に確認するか |

対応状況は `docs/audit/remediation-roadmap.md` を参照。

---

## 1. 総評

ドメイン層の設計品質は高い。以下は本番品質と評価してよい。

- `AiUsageLedger` の予約 → 確定 / 解放の原子性（条件付き `UPDATE` 1発でクォータ超過を弾く）
- Google OAuth の `connection_version` による世代管理（在庫中ジョブの no-op 化）
- Yoyu Money の `lock_version` 楽観ロック + `MoneyAuditService` による監査証跡
- `ownedExists()` による FK 所有権検証（FK 経由の横取りを塞いでいる）
- 全テーブルの `user_id` に `cascadeOnDelete` が徹底されている
- Policy による認可の一貫性（Clear Dawn 系全コントローラで `Gate::authorize()`）

**問題はアプリの外側に集中している。** 認証機能の有効化設定、スケジューラ登録、ストレージ構成、CI — 「コードは良いが、本番として組み上がっていない」というのが総評である。

特に重大なのは **C-1（メール検証が全ルートで無効）** と **C-2（公開登録の停止フラグが機能していない）** で、この2つが重なると「インターネット上の誰でも、捨てアドレスで、非公開のはずのアプリに即時フルアクセスできる」状態になる。

**リリース判定: C（重要修正後リリース）**

---

## Critical

### C-1. メール検証が全ルートで無効（`verified` ミドルウェアが no-op）

#### 現象

`routes/web.php` / `yoyu.php` / `kioku.php` / `settings.php` の全ルートグループに付与された `verified` ミドルウェアが**一切機能しない**。

- 登録直後、メール確認をせずに全機能へアクセスできる
- 確認メールがそもそも送信されない
- プロフィールでメールアドレスを変更しても（`email_verified_at` が `null` になっても）アクセスは制限されない

C-2 と組み合わさると「捨てアドレスで登録 → 即フルアクセス」が成立する。

#### 根本原因

`app/Models/User.php:5`

```php
// use Illuminate\Contracts\Auth\MustVerifyEmail;
```

import がコメントアウトされたまま、`app/Models/User.php:35` の

```php
class User extends Authenticatable implements PasskeyUser
```

に `MustVerifyEmail` が実装されていない。

Laravel の `EnsureEmailIsVerified`（`verified` エイリアスの実体）は次の条件で判定する。

```php
if (! $request->user() ||
    ($request->user() instanceof MustVerifyEmail &&
    ! $request->user()->hasVerifiedEmail())) {
    // ここに来ればブロック
}
```

`User` が `MustVerifyEmail` を実装していない以上、`instanceof` が false になり **条件全体が false になって素通りする**。つまり `verified` は `auth` の重複でしかない。

さらに `config/fortify.php:168` で `Features::emailVerification()` が有効化されているため、設定ファイル上は「検証は効いている」ように見える。これが問題の発見を遅らせている。

確認メールが飛ばないのも同根で、Laravel の `SendEmailVerificationNotification` リスナは `MustVerifyEmail` 実装時のみ動作する。

#### なぜテストで検出されなかったか

`tests/Feature/Auth/EmailVerificationTest.php` は Fortify の検証ルート（`verification.notice` / `verification.verify`）を直接叩いているだけで、
**「未検証ユーザーが保護ルートに入れないこと」を一度も検証していない**。
通っているのに守れていない典型例であり、テストが誤った安心感を与えていた。

#### あるべき姿

- `User` が `MustVerifyEmail` を実装し、`verified` ミドルウェアが実際にゲートとして機能する
- 未検証ユーザーは `verification.notice` にリダイレクトされる
- 登録時・メール変更時に確認メールが送信される
- 「未検証ユーザーが保護ルートで弾かれること」がテストで固定されている

#### 既存ユーザーへの配慮（重要）

`MustVerifyEmail` を実装すると、**`email_verified_at` が `null` の既存ユーザーは即座にロックアウトされる**。
本監査の修正では、実装と同時にデータマイグレーションで既存ユーザーの `email_verified_at` を `created_at` でバックフィルし、
「今まで使えていた人が突然使えなくなる」事故を防ぐ。バックフィル対象は移行時点の既存行のみで、以後の新規登録には適用されない。

#### 修正で得られるもの

- 実在しないメールアドレスでのアカウント作成が成立しなくなる
- アカウント乗っ取り後のメールアドレス書き換えが、検証を挟まずには完了しなくなる
- パスワードリセットの到達先が実在することが保証される
- `config/fortify.php` の設定と実挙動が一致し、設定を読んだ人が騙されなくなる

#### 検証方法

未検証ユーザーで保護ルートへアクセスし `verification.notice` へリダイレクトされることをテストで固定する
（`tests/Feature/Auth/EmailVerificationTest.php` に追加）。

---

### C-2. 公開登録の停止フラグが飾りになっている

#### 現象

`APP_PUBLIC_SIGNUP_ENABLED=false`（既定値）にしても、**`GET /register` と `POST /register` は稼働し続ける**。
UI からリンクが消えるだけで、URL を直接叩けば誰でもアカウントを作成できる。

非公開運用を意図した設定が、実際には何も止めていない。

#### 根本原因

`config/app.php:71` の `public_signup_enabled` を参照しているのは UI 表示だけである。

- `routes/web.php:78-79` — ランディングページの `canRegister` / `canResetPassword`
- `app/Providers/FortifyServiceProvider.php:53-54` — ログイン画面の同項目

一方 `config/fortify.php:166` は無条件である。

```php
'features' => [
    Features::registration(),   // ← フラグを見ていない
    Features::resetPasswords(),
    ...
]
```

Fortify はこの `features` 配列を見てルートを登録するため、**フラグの状態に関わらず登録ルートが生える**。

`throttle:5/hour/IP`（`FortifyServiceProvider.php:104`）は付いているが、これは総量制限であって認可ではない。

#### あるべき姿

`public_signup_enabled` が false のとき、登録ルートそのものが存在しない（404）。
「UI に出さない」と「機能を止める」を混同しない。

#### 修正で得られるもの

- 非公開運用が設定どおりに機能する（招待制・クローズドβの前提が守られる）
- 意図しないユーザー増加による AI コスト（1ユーザーあたり月 $10 のクォータ）の流出を防げる
- 「フラグを false にしたから安全」という運用者の理解が正しくなる

#### 検証方法

`APP_PUBLIC_SIGNUP_ENABLED=false` で `POST /register` が 404 になること、
`true` で従来どおり登録できることの両方をテストで固定する。

#### 派生指摘 C-2b: パスワード再設定が公開登録フラグに巻き込まれている

修正作業中に発見した関連バグ。

`routes/web.php:78` および `app/Providers/FortifyServiceProvider.php:53` は

```php
'canResetPassword' => config('app.public_signup_enabled') && Features::enabled(Features::resetPasswords()),
```

としており、**公開登録を閉じるとパスワード再設定リンクも UI から消えていた**。

パスワード再設定は「既に登録されているユーザーの復旧手段」であり、
「新規ユーザーを受け入れるか」とは何の関係もない。
招待制運用（`public_signup_enabled=false`）にした瞬間、
**既存ユーザーがパスワードを忘れると自力で復旧できなくなる**。

ルート自体は生きているため URL を直接知っていれば到達できるが、
UI からの導線が消えるため実質的に使えない。

**あるべき姿**: 2つのフラグを分離する。登録は `public_signup_enabled` で制御し、
パスワード再設定は常に有効。

**修正で得られるもの**: 招待制運用でも既存ユーザーがパスワードを自力で復旧できる。

---

### C-3. Money CSV インポートが Laravel Cloud で構造的に動作しない

#### 現象

本番（Laravel Cloud）で CSV インポートが**必ず失敗する**。ローカル開発とテストでは通るため、デプロイするまで気づけない。

失敗の仕方も悪く、`ProcessMoneyImportJob` が「Import source file is missing.」で失敗し、
ユーザーには「取り込みに失敗しました」としか見えない。

#### 根本原因

`app/Domain/Yoyu/Money/Services/MoneyCsvImportService.php:32`

```php
private const DISK = 'local';
```

ハードコードで env による上書き手段がない。書き込みは Web コンテナ（同 `:81`）、読み込みはキューコンテナ（`:588`）で行われる。

**Web とキューはファイルシステムを共有しない。** これはこのリポジトリ自身が `config/filesystems.php:104-108` で明記している。

> On Laravel Cloud, MEALS_LABEL_OCR_DISK points at an Object Storage disk instead
> (web and queue containers do not share a filesystem)

食事ラベル OCR 側は `config/meals.php:19` で `MEALS_LABEL_OCR_DISK` として正しく外出しされているのに、
**Money インポートだけがこの学習から取り残されている**。

さらに env を追加するだけでは直らない。`MoneyCsvImportService.php:592`

```php
$absolute = Storage::disk(self::DISK)->path($path);
$file = new SplFileObject($absolute, 'r');
```

`Storage::path()` はローカルドライバ専用 API であり、S3 ディスクでは機能しない。**パーサ本体の書き換えが必要**である。

#### あるべき姿

- ディスクは `config/yoyu.php`（または `config/meals.php` と同様の場所）から env で差し替えられる
- CSV の読み取りは `Storage::readStream()` + `fgetcsv()` で行い、ローカル絶対パスに依存しない
- ローカル開発では従来どおり `local` ディスクで動く（後方互換）

#### 修正で得られるもの

- 本番で CSV インポートが実際に動く
- インスタンス再起動でアップロード済みファイルが消えなくなる
- Web / キューのコンテナ分離という Laravel Cloud の前提に、コードが正しく従うようになる
- ローカルとの環境差分に起因する「本番だけ動かない」クラスのバグが1つ減る

#### 検証方法

`Storage::fake()` で S3 系ドライバを模したディスクを使い、`path()` を経由せずにインポートが完了することをテストで固定する。

---

### C-4. `retry_after` 90秒 < ジョブ `timeout` — 重複実行が構造的に起きる

#### 現象

長時間ジョブが**実行中に別ワーカーへ二重ディスパッチされる**。結果として：

- **Money CSV インポートで金融トランザクションが二重計上される**
- AI 系ジョブ（文字起こし・食事推定・ブリーフィング）で **課金が二重に発生する**
- 同一メモリに対する enrich が競合する

負荷が高いとき・処理が遅いときほど発生しやすく、**本番で最も困るタイミングで壊れる**。

#### 根本原因

`config/queue.php:43,51,71` — 全ドライバで `retry_after` の既定が **90秒**。

対してジョブの `timeout` は次のとおり全て 90 を超えている。

| ジョブ | timeout | 定義箇所 |
| --- | --- | --- |
| ProcessMoneyImportJob | 300 | `app/Domain/Yoyu/Money/Jobs/ProcessMoneyImportJob.php:19` |
| EstimateFoodMenuJob | 180 | `app/Jobs/EstimateFoodMenuJob.php:24` |
| TranscribeMemoryAudioJob | 180 | `app/Domain/Kioku/Jobs/TranscribeMemoryAudioJob.php:27` |
| EnrichMemoryJob | 180 | `app/Domain/Kioku/Jobs/EnrichMemoryJob.php:26` |
| GenerateDailyKiokuLetterJob | 180 | `app/Domain/Kioku/Jobs/GenerateDailyKiokuLetterJob.php:24` |
| SyncGoogleCalendarJob | 120 | `app/Domain/Connectors/Jobs/SyncGoogleCalendarJob.php:27` |
| EstimateFoodPhotoJob | 120 | `app/Jobs/EstimateFoodPhotoJob.php:25` |
| LookupFoodLabelOcrJob | 120 | `app/Jobs/LookupFoodLabelOcrJob.php:31` |

`retry_after` を超えるとキューがジョブを再可視化し、元のワーカーが走っている最中に別ワーカーが同じジョブを取得する。

**`ShouldBeUnique` はこれを防げない。** `ShouldBeUnique` のロックは *dispatch 時* に取得されるものであり、
キュードライバによる「タイムアウトしたとみなしての再解放」には関与しない。

#### 各ジョブの自衛状況

一部のジョブはステータス CAS（Compare-And-Swap）で自衛している。

- `EnrichMemoryJob:144-148` — `whereIn('status', $claimable)->update(...)` の affected 件数で排他
- `TranscribeMemoryAudioJob:150-155` — 同様

しかし **`ProcessMoneyImportJob` にはこの防御がない**。`MoneyCsvImportService::processImport()`（`:250`）は

```php
$locked = MoneyImport::query()->withoutUserScope()->whereKey($import->id)->first();
// lockForUpdate() なし
if ($locked->status === MoneyImportStatus::Completed) { return; }
```

`lockForUpdate()` を取らず、ガードは `Completed` のみ。二重実行時、それぞれのトランザクションは互いの未コミット行を見ないため
`findStrongDuplicate()` による重複検知が機能せず、**同じ CSV 行から2件の取引が作られる**。

#### あるべき姿

- `retry_after` は「最長のジョブ `timeout` + 余裕」を満たす（Laravel 公式も `retry_after` > `timeout` を要求している）
- 金銭に関わるジョブは、キュー設定に依存せず**それ自体が冪等**である（ステータス CAS で二重実行を弾く）
- 設定値は `.env.example` に明記され、運用者が値の意味を理解できる

#### 修正で得られるもの

- 金融データの二重計上が起きなくなる（データ整合性の回復不能な破損を防ぐ）
- AI 課金の二重発生が止まる
- ワーカーのスケールアウト時にジョブ設計が壊れなくなる
- キュー設定を変えても金銭処理の安全性が保たれる（二重の防御）

#### 検証方法

`processImport()` を同一 import に対して2回連続で呼び、取引が1件しか作られないことをテストで固定する。

---

### C-5. CI が存在しない

#### 現象

**Pint / Larastan / PHPUnit（904テスト）/ ESLint / Prettier / Vitest のいずれも、PR で自動実行されない。**
`.github/` ディレクトリ自体が存在しない。

品質ゲートが「人が思い出したら走る」状態であり、リグレッションが本番まで到達しうる。

#### 根本原因

`composer.json` には完成度の高い `ci:check` スクリプトが既に定義されている。

```json
"ci:check": [
    "@wayfinder:generate",
    "npm run lint:check",
    "npm run format:check",
    "npm run types:check",
    "npm run test:js",
    "npm run build:ssr",
    "@test"
]
```

**書いてあるのに、これを起動する仕組みだけが無い。**

#### あるべき姿

PR 作成・更新時に `ci:check` 相当が自動実行され、失敗したらマージできない。

#### 修正で得られるもの

- 904 個のテストが「資産」から「防壁」になる
- `.cursor/rules/static-analysis-zero.mdc` が掲げる「静的解析エラーゼロ方針」が機械的に強制される
- 本監査で修正した内容そのものが、将来のリグレッションから守られる
- レビュー時に人間が形式的な確認をしなくてよくなる

#### 検証方法

PR を作成し、ワークフローが起動して全ジョブが完了することを確認する。

---

## High

### H-1. GET リクエストが書き込み・課金を発生させ、それが prefetch されている

#### 現象

**サイドバーの「お金」にホバーしただけで、定期キャッシュフローの金融レコードが生成される。**
**プロダクトスイッチャーを開いただけで、有料の AI ブリーフィングジョブが dispatch される。**

「画面を見ていないのにデータが作られる」「操作していないのに課金される」という、ユーザーから見て説明不能な挙動になる。

#### 根本原因

**(1) GET で副作用を持つエンドポイントが3つある**

| ルート | 副作用 | 箇所 |
| --- | --- | --- |
| `GET /yoyu/money` | `MoneySetupService::ensureForUser()` + `RecurringCashflowGenerator::generateForUser()`（金融行を作成） | `app/Http/Controllers/Yoyu/Money/MoneyDashboardController.php:22-23` |
| `GET /yoyu` | `syncIfStale()` + `EnsureTodayBriefingService::ensure()`（有料 AI ジョブを dispatch） | `app/Http/Controllers/Yoyu/HomeController.php:42,56` |
| `GET /today` | `GenerateProgramDayPlansService` + `EvaluateRulesForDayService` | `app/Http/Controllers/TodayController.php:28-29` |

**(2) そのルートが prefetch 対象になっている**

- `resources/js/components/os/OsSidebar.vue:150-155` — `href: '/yoyu/money'` に `prefetch: true`
- `resources/js/components/os/ProductSwitcher.vue:97-101` — スイッチャーを開くと `ProductCatalog::all()`（`app/Support/ProductCatalog.php:31` に `route('yoyu.home')` を含む）を全て `router.prefetch()`

`router.prefetch()` は partial reload ではないため、`HomeController::index` のクロージャ props が**全て評価される**。
その中に `resolveBriefing`（`HomeController.php:53-61`）があり、`GenerateYoyuBriefingJob` の dispatch に到達する。

Inertia v3 では prefetch / instant visits が常時有効（`future` 名前空間が廃止されたため）。

#### 影響の正確な範囲

いずれの副作用も**日次冪等**である（ブリーフィングは1日1行、定期キャッシュフローは重複生成されない）。
したがって青天井の課金にはならない。**しかし**：

- 「ユーザーが Yoyu を今日開いた」という事実がデータ上、真でなくなる
- 金融レコードの作成日時が実際の操作と無関係になり、監査証跡の意味が薄れる
- 将来 GET の副作用が冪等でなくなった瞬間、事故が顕在化する

#### あるべき姿

- GET は読み取り専用。状態変更は POST か、スケジューラ経由で行う
- 定期キャッシュフロー生成はスケジュールされたコマンド（`yoyu-money:generate-recurring`）が担う（H-2 と対）
- 副作用を持つルートには prefetch を付けない

チームは既にこの問題を認識している（`docs/dev/n-03-today-get-side-effects-separation.md`）。Today 側は着手済みだが Money / Yoyu が未対応。

#### 修正で得られるもの

- ナビゲーション UI に触れただけで課金・書き込みが起きなくなる
- 「いつ・誰が・何をしたか」がデータから正しく読めるようになる
- prefetch を安心して他ルートへ広げられるようになる（体感速度の改善余地が開く）

#### 段階的対応

本監査では **prefetch の除去とスケジューラ配線（即座に安全側へ倒せる部分）** を実施し、
コントローラから副作用そのものを分離する設計変更は別タスクとして残す（ロードマップ Phase 6）。

---

### H-2. スケジューラに登録されていないコマンドが4つある

#### 現象

**音声メモの文字起こしが `pending` で詰まると、永久に復帰しない。**
ユーザーには文字起こし待ちのスピナーが表示され続ける。

#### 根本原因

`app/Console/Commands/DispatchPendingTranscriptionsCommand.php` は
実装済み・テスト済み（`tests/Feature/Kioku/DispatchPendingTranscriptionsCommandTest.php`）・
ドキュメント済み（`docs/product/kioku-quick-capture.md:153`、`docs/product/kioku-final-remaining-implementation.md:63,303-305`）
であるにもかかわらず、`routes/console.php` に登録されていない。

同様に未登録のコマンド：

| コマンド | 未登録による影響 |
| --- | --- |
| `kioku:transcriptions:dispatch-pending` | 滞留した文字起こしが永久復帰しない |
| `yoyu-money:generate-recurring` | 定期キャッシュフローの生成が **H-1 の GET 副作用のみに依存**する |
| `ai:usage-reconcile` | 台帳と正準合計のドリフトが自動補正されない |
| `kioku:letters:test:prune` | テスト便りが溜まり続ける |

`yoyu-money:generate-recurring` の未登録は H-1 の直接の原因でもある。
「スケジューラでやるべきことを GET でやっている」という構図。

#### あるべき姿

運用に必要な定期処理は全て `routes/console.php` に登録され、
`withoutOverlapping()` と `onOneServer()` が付与されている（既存6件はこの規約に従っている）。

#### 修正で得られるもの

- 文字起こしの滞留が自動復旧する（ユーザーが問い合わせる前に直る）
- 定期キャッシュフローが「ダッシュボードを見たかどうか」に依存しなくなる
- AI 使用量台帳のドリフトが自動で収束する
- 運用者の手動 artisan 実行が不要になる

---

### H-3. 最も高価な AI エンドポイントにレートリミットが無い

#### 現象

`POST /yoyu/chat` を連打することで、月次 AI クォータ（$10）を短時間で使い切れる。
ボットによる自動化があれば数分で枯渇する。

#### 根本原因

`routes/yoyu.php:32`

```php
Route::post('/chat', [HomeController::class, 'chat'])->name('chat');
```

throttle が無い。このエンドポイントは：

- `tier: 'strong'`（`HomeController.php:263`）
- `maxTokens: 1100`
- 履歴30件 × 4000文字 = 最大 120KB の入力（`HomeController.php:241-245`）

つまり **アプリ中で最も高価**である。

**保護の付け方が価格と逆相関している**のが問題の本質：

| エンドポイント | コスト | throttle |
| --- | --- | --- |
| `POST /yoyu/chat` | 最高 | **なし** |
| `POST /yoyu/briefing` | 高 | **なし** |
| `POST /kioku/captures/manual` | 低（AI は非同期・cheap） | 60/分 |
| `POST /meals/photo-estimate` | 中 | 10/分 |

#### あるべき姿

コストに比例した throttle が付く。AI を同期的に呼ぶエンドポイントは最も厳しく制限する。

#### 修正で得られるもの

- 単一ユーザーによるクォータ枯渇が緩和され、意図しない大量消費を防げる
- Anthropic API への突発的なバースト送信が抑制される
- レートリミットの方針が「コストに比例」という一貫した基準になる

---

### H-4. `BelongsToUser` のグローバルスコープが認証不在時に静かに無効化される

#### 現象

現時点で実害は出ていない。**しかし**、将来キューやコマンド内で

```php
Memory::query()->where('status', 'pending')->get();
```

と書いた瞬間、**全ユーザーのデータが返る**。例外も警告も出ない。
「安全のためのスコープ」が、最も危険な文脈（バッチ処理）でだけ外れる。

#### 根本原因

`app/Domain/Shared/Models/BelongsToUser.php:19-25`

```php
static::addGlobalScope('user', function (Builder $builder): void {
    $userId = Auth::id();

    if ($userId !== null) {          // ← 認証が無ければ何もしない
        $builder->where($builder->getModel()->getTable().'.user_id', $userId);
    }
});
```

キューワーカー・artisan・スケジューラでは `Auth::id()` が `null` になるため、スコープが **no-op** になる。
このトレイトは38モデルで使われている。

同様に `:27-31` の `creating` フックも認証不在時は `user_id` を補完しない。

さらに、この設計の帰結として `withoutUserScope()` の明示呼び出しが **217箇所**存在する。
「スコープをバイパスするのが常態」という運用実態は、防御としての意味を失わせている。

#### 現状の安全性について

調査した範囲では、ジョブ・コマンド側は全て主キー指定（`whereKey()`）か明示的な `where('user_id', ...)` を伴っており、
**現時点でのクロステナント漏洩は確認されなかった**。問題は「今は安全」ではなく「安全がコードで保証されていない」ことである。

#### あるべき姿

フェイルオープン（認証が無ければ制限しない）ではなく、**フェイルクローズ**（認証が無ければ何も返さない）にする。
バッチ処理は `withoutUserScope()` を明示的に書くことで意図を宣言する — これは既に217箇所で実践されている習慣であり、
挙動を変えても既存コードは壊れない。

#### 修正で得られるもの

- 「うっかり書いた1行が全ユーザーのデータを返す」というクラスの事故が構造的に起きなくなる
- スコープの意図（強制的なテナント分離）とコードの挙動が一致する
- 新規参加者が `withoutUserScope()` の必要性を、実害を出さずに学べる

#### 対応状況: 見送り（前提作業が必要）

是正作業でフェイルクローズ化を一度実装したが、**呼び出し側の調査により
現状のまま切り替えると実害の大きい退行を生むことが判明したため revert した。**

ジョブ／コマンドから到達する次の経路が、スコープが no-op であることに暗黙的に依存している。

| 経路 | 切り替えた場合に起きること |
| --- | --- |
| `KiokuConciergePilotService:84` の `KiokuConciergeSchedule::updateOrCreate(['user_id' => ...])` | 既存行が引けず、実行のたびにスケジュールを重複作成する |
| `CachedGoogleCalendarProvider:87`（`GenerateYoyuBriefingJob` から到達） | キャッシュ済み予定が 0 件になり、ブリーフィングから予定が黙って消える |
| `KiokuLetterGenerator` の dedupe 再取得経路 | 重複判定が壊れる |

**防ごうとしている事故より大きな障害を作ることになる。**
先に該当23箇所へ `withoutUserScope()` と明示的な `user_id` 条件を入れ切り、
CI が緑であることを確認してから切り替える。詳細と対象一覧は
`docs/audit/remediation-roadmap.md` の Phase 3 / Phase 6 を参照。

---

### H-5. AI モデル ID が旧世代を指し、価格表フォールバックが無言

#### 現象

**(1)** 既定モデルが旧世代（`claude-sonnet-4-6`）を指している。

**(2)** より問題なのは、運用者が `AI_MODEL_STRONG` に価格表未登録のモデル ID を設定した場合、
**課金計算が静かにズレたままクォータが動き続ける**こと。ログ警告すら出ない。
月次上限 $10 が、実際には $10 を意味しなくなる。

#### 根本原因

`config/ai.php:15` および `.env.example`

```php
'strong' => env('AI_MODEL_STRONG', 'claude-sonnet-4-6'),
```

価格表（`config/ai.php:26`）には `claude-sonnet-5` が登録済みなのに、既定値が旧モデルを指している。

`app/Domain/Shared/AI/AiCostCalculator.php` の `ratesFor()` は未知モデルに対し
`'default'`（$3/$15）へ**無言でフォールバック**する。

#### あるべき姿

- 既定モデルは現行世代（Claude 5 系 / Haiku 4.5）を指す
- 価格表に無いモデルが使われたら、フォールバックしたことを警告ログに残す（課金の静かなズレを検知可能にする）

#### 修正で得られるもの

- クォータ上限が実際の金額を正しく反映する
- モデル変更時の価格表更新漏れが、ログから検知できるようになる
- 最新世代モデルの性能・価格を利用できる

---

## Medium

### M-1. CSV の `delimiter` / `encoding` の不正値で 500 エラー

#### 現象
インポート設定で区切り文字に2文字以上（例 `||`）を指定すると、**未捕捉例外で 500**。
不正なエンコーディング名でも同様。ユーザーには何が悪いのか分からない。

#### 根本原因
`app/Http/Requests/Yoyu/Money/ConfigureMoneyImportRequest.php:26-27`

```php
'encoding'  => ['nullable', 'string', 'max:32'],
'delimiter' => ['nullable', 'string', 'max:8'],   // ← 2文字以上を通す
```

- `MoneyCsvImportService.php:602` の `setCsvControl($delimiter)` は PHP 8 で 2バイト以上だと `ValueError` を投げる
- `:686` の `@mb_convert_encoding(...)` の `@` は `ValueError` を抑制しない（PHP 8 で未知エンコーディングは警告ではなく例外）

#### あるべき姿
許可リスト（`Rule::in`）で検証し、不正値はバリデーションエラー（422）としてユーザーに返す。

#### 修正で得られるもの
- 500 が 422 になり、ユーザーが自分で修正できる
- 例外ログのノイズが減る

---

### M-2. `Model::preventLazyLoading()` が未設定

#### 現象
N+1 クエリが**開発中もテスト中も一切検出されない**。
23個の Query クラスと大量の Resource を抱える規模で、性能劣化が本番まで到達する。

#### 根本原因
`app/Providers/AppServiceProvider.php:60-79` の `configureDefaults()` に
`Model::preventLazyLoading()` / `preventSilentlyDiscardingAttributes()` が無い（リポジトリ全体で 0 件）。

`.cursor/rules/sql-memory-performance.mdc` は N+1 防止を規約として掲げているが、**機械的に強制する仕組みが無い**。

#### あるべき姿
非本番環境で遅延ロードを例外にし、開発時点で N+1 を検出する。

#### 修正で得られるもの
- N+1 が「レビューで気づく」ものから「実行すれば分かる」ものになる
- fillable 漏れによる無言のデータ欠落も同時に検出される
- 既存の性能規約が実効性を持つ

---

### M-3. セッションクッキーに Secure 属性が付かない

#### 現象
HTTPS 以外の経路にクッキーが送出されうる。Laravel Cloud は HTTPS 終端のため実害は限定的だが、多層防御が1枚欠けている。

#### 根本原因
`config/session.php:172` は `env('SESSION_SECURE_COOKIE')` を読むが、`.env.example` に当該変数が無いため `null` になり、
Laravel 側で `false` に落ちる。

#### あるべき姿
本番で `SESSION_SECURE_COOKIE=true`。`.env.example` に明記して設定漏れを防ぐ。

#### 修正で得られるもの
- 中間者攻撃・混在コンテンツ経由のセッション奪取リスクが下がる
- 設定の意図がテンプレートから読み取れる

---

### M-4. `.env.example` に本番必須変数が欠けている

#### 現象
運用者が設定すべき変数の存在に気づけない。**C-4 の直接原因**でもある。

#### 根本原因
以下が `.env.example` に無い。

| 変数 | 欠落の影響 |
| --- | --- |
| `DB_QUEUE_RETRY_AFTER` | C-4（重複実行）が既定値のまま放置される |
| `SESSION_SECURE_COOKIE` | M-3 |
| `MEALS_LABEL_OCR_DISK` | `config/meals.php:19` にのみ存在し発見困難 |
| `FILESYSTEM_VIDEOS_DISK` | 同上 |
| `APP_PUBLIC_SIGNUP_ENABLED` | C-2 のフラグの存在自体が知られない |

加えて `.env.example:4` の `APP_DEBUG=true` はテンプレートとして危険側の既定値。

#### あるべき姿
本番で設定が必要な変数は全て `.env.example` に、コメント付きで存在する。

#### 修正で得られるもの
- デプロイ時の設定漏れが減る
- 設定項目がコードを読まずに把握できる

---

### M-5. 金額の桁数上限が無く整数オーバーフローの余地

#### 現象
100桁の数値文字列を送ると `(int)` キャストで `PHP_INT_MAX` に飽和し、残高計算がオーバーフローする。

#### 根本原因
`app/Http/Requests/Yoyu/Money/Concerns/AuthorizesMoneyUser.php:26`

```php
$rules = [$required ? 'required' : 'nullable', 'string', 'regex:/^\d+$/'];
```

桁数上限が無い。`MoneyCashflowService.php:63` の `(int) $data['amount_minor']` で飽和する。

#### あるべき姿
現実的な上限（例: 15桁 = 兆円規模）を検証で強制する。

#### 修正で得られるもの
- 残高計算のオーバーフローが起きなくなる
- 明らかな誤入力が保存前に弾かれる

---

### M-6. 音声ストリーミングに `nosniff` が無い

#### 現象
現時点では安全（MIME は許可リスト検証済み）だが、ブラウザの MIME スニッフィングに対する防御が1枚欠けている。

#### 根本原因
`app/Http/Controllers/Kioku/MemoryController.php:407-410` — `X-Content-Type-Options: nosniff` と
`Content-Disposition` が未付与。

#### あるべき姿
プライベートなユーザーアップロードを返す全経路で `nosniff` を付ける。

#### 修正で得られるもの
- 将来 MIME 検証が緩んだ場合の被害を防ぐ多層防御

---

### M-7. `processImport` が長時間トランザクションで CSV 全件を処理

#### 現象
大きな CSV のインポート中、Money 系テーブルのロックが長時間保持され、他の操作が待たされる。

#### 根本原因
`MoneyCsvImportService.php:264` — 最大 10MB の CSV 全行を単一 `DB::transaction` 内でストリーム処理。
`timeout: 300` と相まってロック保持時間が長い。

#### あるべき姿
チャンク単位のコミット + 行単位の冪等キーで、ロック保持時間を短く保つ。

#### 修正で得られるもの
- インポート中も他の Money 操作が滞らない
- 部分的な失敗からの再開が可能になる

**本監査では設計変更が大きいため Phase 6（別タスク）とする。**

---

## Low

| ID | 内容 | 箇所 |
| --- | --- | --- |
| L-1 | `AttachToClearDawnGoal` / `SendToYoyuTask` が空実装スタブで参照ゼロ（デッドコード） | `app/Domain/Kioku/Commands/` |
| ~~L-2~~ | ~~`target="_blank"` に `rel="noopener"` が無い~~ → **誤検知（対応不要）**。行単位 grep による誤りで、実際は3箇所とも次行に `rel="noopener noreferrer"` が付いている。タグ単位で再走査したところ該当0件 | — |
| L-3 | `destroy(Metric $metric, MetricRecord $metricRecord)` が両者の親子関係を検証していない | `MetricRecordController.php:213` |
| L-4 | `Auth::logout()` → `$user->delete()` がトランザクション外 | `ProfileController.php:66-70` |
| L-5 | `videos:prune-pending` のみ `withoutOverlapping()` / `onOneServer()` が無い | `routes/console.php:11` |
| L-6 | `v-html` の使用（Fortify 生成 SVG のため実害無し。由来をコメントで固定すべき） | `TwoFactorSetupModal.vue:175` |
| L-7 | `AiGateway` に 429 / 5xx のリトライが無い | `AiGateway.php:97-101` |

---

## セキュリティ総括（OWASP Top 10）

| 項目 | 評価 | 根拠 |
| --- | --- | --- |
| A01 アクセス制御の不備 | **不合格** | C-1 / C-2。ただしリソース単位の IDOR は検出されず（下記） |
| A02 暗号化の失敗 | 良好 | OAuth トークンは `Connector.php:70-71` で `encrypted` キャスト + `$hidden`。M-3 のみ |
| A03 インジェクション | 良好 | 生 SQL は `AiUsageLedger.php:41-57` の1箇所のみで完全パラメータ化。`v-html` は1箇所。エクスポートは JSON のため CSV 式インジェクション該当なし |
| A04 安全でない設計 | 要改善 | H-1 / H-4 |
| A05 設定ミス | **不合格** | C-3 / C-5 / M-3 / M-4 |
| A07 認証の失敗 | 要改善 | レートリミッタ自体は良好（`FortifyServiceProvider.php:86-114`）。C-1 / C-2 が問題 |
| A09 ログ・監視の失敗 | 要改善 | エラートラッキング（Sentry 等）未統合。`MoneyAuditService` の監査ログは充実 |

### IDOR について（重要な良い結果）

`routes/web.php:52-70` のルートモデルバインディングはユーザースコープを持たない
（`whereKey($value)->firstOrFail()`）。一見危険に見えるが、**全経路で二重・三重の防御が確認された**。

- Clear Dawn 系: 全コントローラで `Gate::authorize()`
  （`RoutineItemController.php:47,81,103`、`RoutinePlanController.php:32,86,104`、`RoutineSessionController.php:22,36,47,58` 他）
- Kioku 系: `LetterController.php:137-140` の `authorizeOwner()`、`MemoryController.php:399,417` の `abort_unless`
- Yoyu Money 系: `EnsuresMoneyOwnership::ensureOwned()` + `BelongsToUser` グローバルスコープ
- **FK 経由の横取りも塞がれている** — `ownedExists()`（`AuthorizesMoneyUser.php:16-19`）が
  `category_id` / `account_id` / `credit_card_id` 等すべてに `where('user_id', ...)` 付き `Rule::exists` を適用

テストにも cross-user ケースが7ファイル、403/404 アサーションが59件存在する。
**認可の実装規律は高水準であり、問題は「誰がアカウントを持てるか」という入口側にある。**

---

## 保守性・技術的負債

### 良い点
- `Controller → FormRequest → Service/Query → Resource` の層構造が591ファイル全体で一貫。Fat Controller はほぼ皆無
- Enum 33個、値オブジェクト（`AiMoney` / `MoneyAmount`）、`Domain/{Kioku,Yoyu,Shared}` の境界が明確
- ADR 13本で設計判断が追跡可能
- PHPDoc の配列シェイプ型注釈が徹底

### 負債
| 分類 | 内容 |
| --- | --- |
| 設計 | `BelongsToUser` のフェイルオープン（H-4）と `withoutUserScope()` 217箇所 |
| 設計 | GET での書き込み（H-1） |
| 設計 | `Domain/` 系と `app/Models` + `app/Services` 系の2系統アーキテクチャ併存 |
| 命名 | ルートバインディング名 `item` / `p` / `s` / `ss` / `bl`。`routes/kioku.php:34` に衝突回避コメントが既に存在し、負債が顕在化している |
| コード | `app/Services/` 直下76クラスのフラット構造（1メソッドのみのクラスが大半） |
| コード | 1000行超の Vue SFC 4件（`Yoyu/Index.vue` 1670行、`Meals/Index.vue` 1590行、`StepEditorDialog.vue` 1271行、`BarcodeLookupModal.vue` 1209行） |
| 運用 | CI 不在（C-5）、エラートラッキング未統合、`.env.example` の乖離（M-4） |

---

## 未完成機能

| 対象 | 状態 |
| --- | --- |
| `AttachToClearDawnGoal` / `SendToYoyuTask` | 空実装スタブ、参照ゼロ |
| `kioku:transcriptions:dispatch-pending` | 実装・テスト・文書化済みだが未配線（H-2） |
| `yoyu-money:generate-recurring` | 同上。GET 副作用で代替されている |
| `ai:usage-reconcile` / `kioku:letters:test:prune` | 同上 |
| Kioku コンシェルジュ | `KIOKU_CONCIERGE_ENABLED=false` でフラグ封鎖中（意図的・正しい運用） |
| Inertia SSR | `INERTIA_SSR_ENABLED=false`、理由をコメントで明記済み（意図的） |
| E2E テスト | Dusk / Playwright / Cypress いずれも未導入 |

TODO / FIXME マーカーはリポジトリ全体で2件のみ。
ただし**「未完成であることがマーカーとして残っていない」未配線コマンド群のほうが危険**である。

---

## 監査の限界（明示）

- 依存パッケージ（dev）がネットワーク制約で取得できず、**テストスイート・PHPStan・Pint の実行は監査時点では行えていない**。
  本レポートの指摘はすべて静的解析とコード読解に基づく。
- 実行時プロファイリング（実 DB に対する N+1 計測、負荷試験）は未実施。
- ペネトレーションテストは未実施。
