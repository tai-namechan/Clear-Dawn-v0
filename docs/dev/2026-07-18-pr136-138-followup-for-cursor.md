# PR #136〜138 レビュー対応 引き継ぎ（Cursor向け）

作業ブランチ: `claude/review-pr-136-138-4cq6um`（最新コミット `2e1de94`）
元レビュー: `docs/dev/2026-07-18-pr136-138-code-review.md`

## すでに完了・コミット済み（`2e1de94`）

REV-01〜05, 07〜10, 15〜17 のバグ修正 + テスト追加、CSVインポートE2Eテスト、シミュレーションstale-applyテストを実装済み。詳細はコミットメッセージと元レビューを参照。

### 未検証の1点（重要）

このセッションの実行環境には **PHP bcmath 拡張がインストールされておらず**（apt がプロキシで403、composerのgithub認証も難あり）、`app/Domain/Yoyu/Money/Support/MoneyAmount.php` を経由する処理（`bccomp`/`bcadd`/`bcsub`）を含むテストが実行できませんでした。具体的には以下が**コード変更は完了しているが未実行**です。

- `tests/Feature/Yoyu/MoneyHttpTest.php::test_settle_with_stale_lock_version_returns_409`（REV-10 の検証テスト）
- Money関連の他の既存テスト（`MoneyMarginFeatureTest` 等）もこのセッションの一部実行でしか確認できていない可能性があるため、bcmath が有効な環境で **`php artisan test --compact tests/Unit/Yoyu/Money tests/Feature/Yoyu`** を必ず再実行して確認してください。

## 残作業（未着手）

### 1. S-02: 「今日」の日付判定をユーザーTZ基準に統一（仕様提案・採用決定済み）

ユーザーから「日本時間で対応」の指示あり。実装方針：

- `app/Domain/Yoyu/Support/UserTimezoneResolver.php` のフォールバックを `UTC` → `Asia/Tokyo` に変更（`config('app.timezone')` を経由している箇所、もしくはこのクラス自体のデフォルト値）。
- 以下のコントローラ/サービスで `now()->toDateString()` や `Carbon::parse($request->input('date', now()->toDateString()))` のような **UTC基準の「今日」決定** を、`UserTimezoneResolver::for($user)` を使ったユーザーTZ基準に置き換える：
  - `app/Http/Controllers/TodayController.php:24`（REV-11の date バリデーション追加とセットで対応するとよい）
  - `app/Http/Controllers/DailyCheckinController.php:20`
  - `app/Http/Controllers/MealEntryController.php`（index の `date`/`from`/`to` デフォルト）
  - `app/Services/EvaluateRulesForDayService.php`、`GenerateProgramDayPlansService.php` を呼ぶ側のコントローラ
  - `GetProgramRoadmapQuery.php:33` の `Carbon::today()`
  - `app/Models/PersonalProfileEntry.php` の `currentFor`（`Carbon::today()` 使用箇所）
- `UserTimezoneResolver` は `App\Domain\Yoyu\Support` 配下にあるが、self-management系からも使う横断的ユーティリティになるため、名前空間はそのままでよいが `app/Http/Controllers` から `use App\Domain\Yoyu\Support\UserTimezoneResolver;` で参照する形で問題ない（既存の money 側と同じ使い方）。
- テスト: JSTで「朝6時にアクセスした場合、UTC日付ではなくJST日付が『今日』になる」ことを検証するテストを最低1本追加（`Carbon::setTestNow()` でUTC深夜時間帯を固定して確認）。
- 併せて **REV-11**（`/today?date=` の未検証入力）もこの修正の中で対応すると効率的（`$request->validate(['date' => ['sometimes', 'date']])` を追加）。

### 2. Docs修正

- **REV-06**: ADR番号衝突の解消。
  - `docs/adr/0010-program-layer-on-routine-engine.md` → `docs/adr/0012-program-layer-on-routine-engine.md` にリネーム（0011は`five-data-kinds-and-rule-engine.md`が使用済みのため0012を採番）。
  - `docs/adr/README.md` の一覧テーブルに0011・0012の行を追加（現状0010=yoyu-moneyのみ記載で漏れている）。
  - リネームしたファイル内、および他ファイルからの `0010-program-layer` への参照を `grep -rn "0010-program-layer" docs/ app/` で洗い出して追従修正。
  - 両ADR冒頭にある stale なベースコミット参照（`main@62cac50…`）を削除 or 現状に更新。
- **REV-14**: `docs/product/screens/meals.md` の「いつもの食事」の記述を、現状はプレースホルダ（検索タブを開くだけ）である旨に修正するか、実装するか（未実装なら doc に "未実装（検索へのショートカット）" と明記）。
- **S-01**: `docs/product/yoyu-money-margin-design.md` §4 に、当日期日の収入除外・支出算入という非対称仕様（`MarginCalculator.php:314-325`）を明文化する一文を追加。
- **S-05**: `docs/product/screens/records.md` または `today-ops.md` に、チェックインAPIが部分更新（送信フィールドのみ更新、未送信は既存値保持）である旨を明記（REV-01実装と対応）。

### 3. 元レビュー報告書の更新

`docs/dev/2026-07-18-pr136-138-code-review.md` のサマリー表に、各REV項目の対応状況（fixed/pending）を追記する。

### 4. 最終検証とPR作成

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Unit/Yoyu/Money tests/Feature/Yoyu
php artisan test --compact tests/Feature/MealEntryTest.php tests/Feature/MetricRecordTest.php tests/Feature/TodayOpsPhaseTest.php tests/Feature/ProgramDayPlanGenerationTest.php tests/Feature/ProgramVersionReviseTest.php
npm run types:check
```

全て green を確認後、`claude/review-pr-136-138-4cq6um` → `main` のPRを作成する（PRテンプレートがあれば準拠、なければ通常の形式で）。
