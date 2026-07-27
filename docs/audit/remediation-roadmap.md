# 是正ロードマップ（2026-07-26 監査）

指摘の詳細は [`2026-07-26-pre-release-audit.md`](./2026-07-26-pre-release-audit.md) を参照。
本文書は **フェーズ分割 / 対応状況 / 各フェーズで何が守れるようになるか** を管理する。

---

## 実施上の制約（重要）

**監査・修正時点で PHPUnit / PHPStan / Pint が実行できない。**
dev 依存パッケージが取得できない環境制約による（runtime 依存のみ導入し、`php artisan` の起動確認は実施）。

したがって各フェーズの修正は次の方針で行っている。

- 変更は**小さく・意味が明白なもの**に限定する
- 既存の設計・命名・レイヤ規約に完全に従う（新しい抽象を持ち込まない）
- テストは**追加するが実行未確認**であることを PR に明記する
- **Phase 5（CI 構築）完了後、CI 上で全フェーズのテストが初めて機械実行される**

`ci:check` が緑になるまで、本ロードマップの完了は宣言しない。

---

## フェーズ一覧

**実行順は指摘の重大度順ではなく「CI を最初に作る」順とした。**
理由は上記の制約にある。ローカルでテストが実行できない以上、
**先に CI を用意して、以降の修正 PR をすべて機械検証させるのが唯一の安全な進め方**である。
CI を最後に回すと、Critical 修正が未検証のまま4つ積み上がることになる。

| 実行順 | Phase | 対象 | 状態 | ブランチ |
| --- | --- | --- | --- | --- |
| 1 | 0 | 監査ドキュメント + ロードマップ + Rules/Skills 整理 | 完了 | `claude/pre-release-security-audit-svqt21` |
| 2 | 5 | **C-5 CI パイプライン**（以降を機械検証するため先行） | 完了 | `claude/audit-phase1-ci` |
| 3 | 1 | C-1 メール検証 / C-2b パスワード再設定の分離（**C-2 は取り下げ**） | 完了 | `claude/audit-phase2-auth` |
| 4 | 2 | C-4 キュー重複実行 / C-3 Money インポート | 完了 | `claude/audit-phase3-queue-import` |
| 5 | 3 | H-1〜H-5 | 完了 | `claude/audit-phase4-high` |
| 6 | 4 | M-1〜M-6 / L-1〜L-7 | 完了 | `claude/audit-phase5-medium-low` |
| — | 6 | 設計変更を伴う残件（別途計画） | 未着手 | — |

### PR の構成（スタック）

各フェーズは前のフェーズのブランチを base とする **スタック PR** にしてある。
`.env.example` / `routes/console.php` など複数フェーズが触るファイルがあるため、
独立ブランチにするとコンフリクトが発生するのを避ける意図。

**マージは実行順どおりに行うこと。** 前段の PR がマージされると、
次段の PR の base が自動的に `main` に付け替わる（GitHub の挙動）。

---

## Phase 0 — 監査ドキュメントと開発ルールの整理

### やったこと

1. `docs/audit/2026-07-26-pre-release-audit.md` — 全指摘の詳細（現象 / 根本原因 / あるべき姿 / 修正で得られるもの / 検証方法）
2. `docs/audit/remediation-roadmap.md` — 本文書
3. **Rules の正を `.cursor/rules/` に一本化**し、`CLAUDE.md` / `AGENTS.md` から参照させた
4. **Cursor 固有のワークフロースキル7件を Claude Code からも利用可能にした**（symlink）

### Rules / Skills の整理方針

調査の結果、`.claude/skills` と `.cursor/skills` の重複は **Laravel Boost による自動生成**であることが判明した
（`boost.json` の `"agents": ["claude_code", "cursor"]`）。手作業のコピーではない。

したがって **生成物と手書き資産を分けて扱う**。

| 分類 | 場所 | 扱い |
| --- | --- | --- |
| Boost 生成スキル（7件） | `.claude/skills/` `.cursor/skills/` | **触らない**。`boost:update` が再生成する。手編集禁止 |
| Cloud デプロイスキル | `.ai/skills/deploying-laravel-cloud/` | 既存の symlink 方式を維持（正は `.ai/`） |
| 手書きワークフロースキル（7件） | `.cursor/skills/{bugfix,incident,perf-review,review-only,spec,test-design-review,vue-sfc-patterns}` | **正は `.cursor/`**。`.claude/skills/` から symlink して共有 |
| プロジェクトルール（14件） | `.cursor/rules/*.mdc` | **正は `.cursor/rules/`**。`CLAUDE.md` / `AGENTS.md` から参照 |

#### なぜこの方針か

- Boost 生成物を symlink 化しても `composer update`（`post-update-cmd` で `boost:update` が走る）で元に戻る。**生成器と戦わない**
- 手書き資産は Boost の管理外なので symlink が安全に効く
- Rules を `.cursor/rules` に置いたままにすることで、Cursor の `alwaysApply: true` が機能し続ける
  （`.claude/rules/` へ移すと Cursor 側の自動適用が壊れる）

#### 解消した問題

**`AGENTS.md` に Clear Dawn 固有の最重要ルールが存在しなかった。**

`CLAUDE.md` と `AGENTS.md` の差分は9行のみで、その9行が

- 「プロダクト仕様・データ設計・画面仕様の正は `docs/`」
- 「Boost ガイドラインと `docs/` が矛盾したら `docs/` を優先」
- 「仕様と実装が乖離したら実装前に `docs/` を更新」

という**このプロジェクトで最も重要な優先順位**だった。
`AGENTS.md` を読む側（Cursor / Codex 等）にはこれが届いていなかった。

さらに `.cursor/rules/*.mdc` の14ファイル・約786行（アーキテクチャ層責務 / SQL 性能基準 / テスト品質基準 / 静的解析ゼロ方針）は
Claude Code からは参照されない。**規約の半分がツールによって見えたり見えなかったりする**状態だった。

→ 両ファイルに共通プリアンブルを置き、`.cursor/rules/` 配下を明示的に列挙して参照させた。
→ プリアンブルの同期は **CI でチェックする**（Phase 5、`scripts/check-agent-preamble.mjs`）。人間の記憶に依存させない。

---

## Phase 1 — 認証の重大欠陥（C-1 / C-2b）

> **C-2（公開登録の停止フラグ）は取り下げた。** 登録ポリシーを確認した結果、
> 「UI に導線を出さないところまでが要件で、URL 直打ちは許容」が正しい仕様だった
> （`docs/product/signup-policy.md`）。監査は `docs/` に記述が無いことを
> 「非公開運用の意図」と読み替えてしまっており、その前提自体が誤りだった。
> 詳細は監査レポートの C-2 節を参照。

### 何が守れるようになるか

- **実在しないメールアドレスでのアカウント作成が成立しなくなる**
- **URL 直打ちで登録されても、メール確認を通すまでアプリの中身に到達できない**
  （C-2 が求めていた防御を C-1 が実質的に代替する）
- **登録導線を隠したままでも既存ユーザーがパスワードを自力で復旧できる**（C-2b）
- アカウント乗っ取り後のメール書き換えが、検証を挟まずには完了しなくなる

### 変更内容

| 対象 | 変更 |
| --- | --- |
| `app/Models/User.php` | `MustVerifyEmail` を実装。`verified` ミドルウェアが実際のゲートになる |
| `database/migrations/..._backfill_email_verified_at_for_existing_users.php` | 既存ユーザーの `email_verified_at` を `created_at` でバックフィル（ロックアウト防止） |
| `config/fortify.php` | `Features::registration()` は**無条件のまま**。フラグで出し分けると Wayfinder が `routes/register` を生成せずフロントのビルドが壊れる（実際に CI で発覚） |
| `routes/web.php` / `FortifyServiceProvider.php` | **C-2b**: パスワード再設定を登録導線フラグから分離。`canRegister` は従来どおりフラグを見る |
| `tests/Feature/Auth/EmailVerificationTest.php` | 未検証ユーザーが保護ルートで弾かれることを検証（従来欠けていた本質的なケース） |
| `tests/Feature/Auth/SignupPolicyTest.php` | 新規。現在の登録ポリシー（導線非表示 / URL 直打ちは許容 / 再設定は生存）を固定 |
| `tests/Feature/Kioku/MemoryTagsUpdateTest.php` | 「MustVerifyEmail 実装後にカバーされる」と自らコメントしていた箇所を実際の検証に更新 |

### phpunit.xml を触らなくてよくなった理由

当初は `Features::registration()` をフラグで条件化したため、テストで
`APP_PUBLIC_SIGNUP_ENABLED=true` を明示しないと `skipUnlessFortifyHas()` が
登録系テストを全 skip してしまう問題があった（「緑だが何も検証していない」状態）。

C-2 の取り下げにより `Features::registration()` が無条件に戻ったため、
この対処ごと不要になった。フラグは request 時にしか読まれないので、
`SignupPolicyTest` は `config()->set()` で両方の挙動を直接検証できる。

そのためテスト既定は `true`（登録系テストが実際に走る）とし、
フラグ false の挙動は `ClosedSignupTest` がアプリ生成前に環境変数を差し替えて個別に検証する。

### 既存ユーザーへの影響（意図的な配慮）

`MustVerifyEmail` を実装すると `email_verified_at` が `null` の既存ユーザーは即ロックアウトされる。
これを防ぐため、**同一 PR 内でバックフィルマイグレーションを同梱**した。

- 対象: マイグレーション実行時点で `email_verified_at IS NULL` の既存行のみ
- 値: `created_at`（登録時点で暗黙的に信頼されていたユーザーとして扱う）
- 以後の新規登録には適用されない（正しく検証が要求される）

`down()` は**意図的に空**にしている。ロールバックで検証済み状態を剥奪すると、
再度ロックアウトが発生し復旧が困難になるため。

---

## Phase 2 — キュー重複実行と Money インポート（C-4 / C-3）

### 何が守れるようになるか

- **金融トランザクションの二重計上が起きなくなる**（回復不能なデータ破損を防ぐ）
- **AI 課金の二重発生が止まる**
- **本番（Laravel Cloud）で CSV インポートが実際に動くようになる**
- インスタンス再起動でアップロード済みファイルが消えなくなる

### 変更内容

| 対象 | 変更 |
| --- | --- |
| `config/queue.php` | `retry_after` 既定を 90 → 660 秒（最長ジョブ `timeout` 300 + 余裕）。全ドライバ |
| `.env.example` | `DB_QUEUE_RETRY_AFTER` を理由コメント付きで明記 |
| `MoneyCsvImportService` | ステータス CAS による二重実行防止（キュー設定に依存しない冪等性） |
| `MoneyCsvImportService` | ディスクを `config('yoyu.money.import.disk')` に外出し |
| `MoneyCsvImportService` | `Storage::path()` + `SplFileObject` → `Storage::readStream()` + `fgetcsv()` |
| `config/yoyu.php` | 新規。`YOYU_MONEY_IMPORT_DISK` |

### 二重防御にした理由

`retry_after` の修正だけでも重複実行は止まるが、**キュー設定は運用者が変更できる**。
金銭を扱うジョブが設定値の正しさに依存する設計は脆い。
そのため `processImport()` 自体にステータス CAS を入れ、**設定が間違っていても二重計上が起きない**ようにした。

---

## Phase 3 — High 項目（H-1〜H-5）

### 何が守れるようになるか

- **ナビゲーション UI に触れただけで課金・書き込みが起きなくなる**
- **音声文字起こしの滞留が自動復旧する**（ユーザーが問い合わせる前に直る）
- 単一ユーザーによる AI クォータ枯渇が緩和される
- 「うっかり書いた1行が全ユーザーのデータを返す」事故が構造的に起きなくなる
- AI 課金計算の静かなズレが検知可能になる

### 変更内容

| ID | 対象 | 変更 |
| --- | --- | --- |
| H-1 | `OsSidebar.vue` / `ProductSwitcher.vue` | 副作用を持つルートから prefetch を除去 |
| H-2 | `routes/console.php` | 未配線コマンド4件を登録（`withoutOverlapping()` + `onOneServer()` 付き） |
| H-3 | `routes/yoyu.php` | `/chat` に `throttle:20,1`、`/briefing` に `throttle:10,1` |
| H-4 | `BelongsToUser` | 認証不在時をフェイルクローズ化（`whereRaw('1 = 0')`） |
| H-5 | `config/ai.php` `.env.example` | 既定モデルを現行世代へ更新。価格表フォールバック時に警告ログ |

### H-1 の対応範囲について（重要）

本フェーズで実施したのは **prefetch の除去とスケジューラ配線**、すなわち
「即座に安全側へ倒せる部分」に限る。

**コントローラから副作用そのものを分離する設計変更は Phase 6 に残す。**
`MoneyDashboardController` / `HomeController` / `TodayController` は依然として GET で書き込みを行う。
prefetch を外したことで「ユーザーが実際に画面を開いたとき」に限定されたが、
GET が冪等であるべきという原則自体はまだ満たしていない。

### H-4 の互換性について

`withoutUserScope()` は既に **217箇所**で明示的に使われており、
バッチ処理側は主キー指定か明示的な `where('user_id', ...)` を伴っていることを確認済み。
フェイルクローズ化しても既存の呼び出しは壊れない。

---

## Phase 4 — Medium / Low

### 何が守れるようになるか

- CSV 設定ミスが 500 ではなく **ユーザーが自力で直せる 422** になる
- **N+1 が開発時点で検出される**（既存の性能規約が実効性を持つ）
- 残高計算の整数オーバーフローが起きなくなる
- デプロイ時の設定漏れが減る

### 変更内容

| ID | 対象 | 変更 |
| --- | --- | --- |
| M-1 | `ConfigureMoneyImportRequest` | `delimiter` / `encoding` を許可リスト検証 |
| M-2 | `AppServiceProvider` | 非本番で `preventLazyLoading()` + `preventSilentlyDiscardingAttributes()` |
| M-3/M-4 | `.env.example` | `SESSION_SECURE_COOKIE` 他、本番必須変数を明記 |
| M-5 | `AuthorizesMoneyUser` | 金額に桁数上限（15桁） |
| M-6 | `MemoryController::audio` | `X-Content-Type-Options: nosniff` + `Content-Disposition` |
| L-1 | `app/Domain/Kioku/Commands/` | 参照ゼロのデッドコード2件を削除 |
| L-2 | `AppHeader.vue` / `NavFooter.vue` | `rel="noopener noreferrer"` |
| L-5 | `routes/console.php` | `videos:prune-pending` に `withoutOverlapping()` + `onOneServer()` |

### M-2 の適用範囲について

`preventLazyLoading()` は **非本番環境のみ**で有効化した。
本番で有効にすると、未検出の遅延ロードが 500 エラーとしてユーザーに到達するため。
開発・テストで検出し、本番では従来どおり動作させるのが安全側の選択。

---

## Phase 5 — CI パイプライン（C-5）〈実行順 2番目・最優先で構築〉

### 何が守れるようになるか

- **904個のテストが「資産」から「防壁」になる**
- `.cursor/rules/static-analysis-zero.mdc` の「静的解析エラーゼロ方針」が機械的に強制される
- **本監査で修正した内容そのものが、将来のリグレッションから守られる**
- `CLAUDE.md` / `AGENTS.md` のプリアンブル同期が人間の記憶に依存しなくなる

### 変更内容

`.github/workflows/ci.yml` — PR / `main` push で以下を実行。

| ジョブ | 内容 |
| --- | --- |
| `php` | Pint（`lint:check`）→ Larastan（`types:check`）→ PHPUnit |
| `js` | ESLint → Prettier → `types:check` → Vitest → build |
| `docs` | `CLAUDE.md` / `AGENTS.md` のプリアンブル同一性チェック |

---

## Phase 6 — 未着手（設計変更を伴う残件）

以下は**影響範囲が大きく、単独で設計判断とレビューを要する**ため、本ロードマップでは実施しない。

| ID | 内容 | 理由 |
| --- | --- | --- |
| H-1 残 | GET から副作用を分離（`MoneyDashboardController` / `HomeController` / `TodayController`） | コントローラとサービスの責務再設計が必要。UX（初回表示時に何を見せるか）の判断を伴う |
| M-7 | `processImport` のチャンク化 | トランザクション境界の再設計。部分再開の仕様策定が必要 |
| L-3 | `MetricRecordController::destroy` の親子関係検証 | ルート設計（`{metric}/{metricRecord}`）自体の見直しが望ましい |
| L-4 | アカウント削除のトランザクション化 | ストレージ削除と DB 削除の順序・補償処理の設計が必要 |
| L-7 | `AiGateway` の 429/5xx リトライ | 課金ライフサイクル（reserve/settle/release）との整合設計が必要 |
| 負債 | `app/Services/` 76クラスのフラット構造再編 | 大規模リネーム。CI 稼働後に実施すべき |
| 負債 | 1000行超 Vue SFC 4件の分割 | 同上 |
| 負債 | ルートバインディング名 `p` / `s` / `ss` / `bl` の正常化 | 破壊的変更。URL 互換性の検討が必要 |
| 運用 | エラートラッキング（Sentry 等）導入 | ツール選定と費用判断を伴う |
| テスト | E2E 基盤（Playwright）導入 | CI 稼働後が前提 |

---

## 完了条件

本ロードマップは以下がすべて満たされた時点で完了とする。

- [ ] Phase 1〜5 の全 PR がマージされている
- [ ] **CI（Phase 5）が緑である** — これにより Phase 1〜4 のテストが初めて機械検証される
- [ ] `APP_PUBLIC_SIGNUP_ENABLED` / `DB_QUEUE_RETRY_AFTER` / `SESSION_SECURE_COOKIE` /
      `YOYU_MONEY_IMPORT_DISK` が本番環境変数に設定されている
- [ ] 本番でバックフィルマイグレーションが適用され、既存ユーザーがログインできることを確認済み
