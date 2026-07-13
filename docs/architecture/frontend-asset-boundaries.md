# フロントエンド・アセット境界

Clear Dawn OS（ClearDawn / ヨユウ / キオク / Videos）のフロントエンドが、画面や機能を何百件追加しても、無関係な画面の初期ロード・preload 件数・HTTP レスポンスヘッダーを増やさないための設計。

## 背景（インシデント）

production で Laravel の `AddLinkHeadersForPreloadedAssets` middleware が build manifest 全体（200+ chunks）を `Link` ヘッダーへ展開し、約 61,549 bytes に達した。proxy の upstream header buffer（既定 4–8KB）を超え、Cloudflare 502 が発生した。

hotfix（PR #112 / #113 backport）で以下を実施済み。

- `AddLinkHeadersForPreloadedAssets` を web middleware group から除外（[bootstrap/app.php](../../bootstrap/app.php)）
- `/login` の `Link` ヘッダー 4KB 以下・middleware 未登録・Set-Cookie 維持のガードテスト（[tests/Feature/LoginPageHeadersTest.php](../../tests/Feature/LoginPageHeadersTest.php)）

この障害の本質は「HTTP ヘッダーへアセット一覧を載せる方式は manifest（≒画面数）に比例して壊れる」こと。本ドキュメントは、その再発防止を含むアセット設計の不変条件を定める。

## 不変条件

> 新しいページを追加しても、無関係な画面・プロダクトの初期 static import、modulepreload 件数、初期通信量を増加させない。

## 要求事項

### FR-ASSET-001：ページ追加耐性

新しいページを追加しても、無関係な画面の初期アセット、preload 件数、初期通信量を増加させない。

- Inertia のページ解決は非 eager とする。`@inertiajs/vite` プラグインが注入するページ resolver は既定で lazy（非 eager `import.meta.glob`）であり、`inertia({ lazy: false })` への変更や `eager: true` の glob を禁止する。
- `/login` 等の HTML に出る `modulepreload` は「entry の static closure + 現在ページの static closure」のみで構成され、総ページ数に比例しない。

### FR-ASSET-002：プロダクト境界

ClearDawn / ヨユウ / キオク / Videos 等のプロダクト固有コードは、そのプロダクトを初めて開いた時点で読み込む。

- プロダクト固有コード（`pages/Kioku/**`, `pages/Yoyu/**`, `pages/Videos/**`, `components/kioku/**`, `components/yoyu/**`, `components/video/**`, プロダクト固有 composable）を entry から static import しない。
- 境界を越えて共有してよいのは、型（type-only import）、wayfinder 生成の route ヘルパー、共通 UI・共通 composable のみ。

### FR-ASSET-003：共通 entry

認証、Inertia、OS シェル（レイアウト・サイドバー・プロダクトスイッチャー）、共通 UI だけを初期 entry（`resources/js/app.ts`）の static 依存として許可する。

### FR-ASSET-004：ヘッダー予算

HTTP `Link` ヘッダーは原則 0 bytes、最大 4KB とする。manifest や画面数に比例して増加する middleware を登録しない。preload は `@vite` が HTML 内へ出力するものだけを使う。

### FR-ASSET-005：static dependency 予算

manifest の総 chunk 数ではなく、entry から `imports` だけで到達する static closure へ予算を設定し、CI 相当のチェックで監視する。

- 予算定義: [scripts/asset-budget.json](../../scripts/asset-budget.json)
- チェッカー: `npm run assets:check`（[scripts/check-vite-asset-budget.mjs](../../scripts/check-vite-asset-budget.mjs)）
- `dynamicImports` の先は初期ロードに含めない。ページ追加で manifest 総 chunk 数が増えるのは正常であり、制限しない。

## 現在の構造（2026-07-13 監査）

- entry は `resources/js/app.ts`（JS）と `resources/css/app.css`（CSS）の 2 つのみ。複数 Vite entry には分割しない。
- ページ解決は `@inertiajs/vite` の自動 resolver（非 eager glob）。`app.ts` に resolver の手書きコードはない。
- `app.ts` が static import するのはレイアウト 4 種（App / Auth / OsShell / settings）、テーマ・フラッシュ初期化のみ。
- OS シェル（`OsShellLayout` → `OsSidebar` / `ProductSwitcher`）がプロダクトから参照するのは route ヘルパーと type-only import だけで、プロダクト固有の実装コードへは到達しない。
- echarts（約 500KB）はグラフ系ページの chunk からのみ static import され、初期 closure には入らない。

### ベースライン実測値（2026-07-13, production build）

| 指標 | 実測値 |
|---|---|
| HTTP Link ヘッダー | 0 bytes |
| `app.ts` static closure | 6 chunks / 458,094 bytes / gzip 145,603 bytes |
| `app.css` static closure | 1 chunk / 161,299 bytes / gzip 24,799 bytes |
| manifest 総 chunk 数 | 204（増加を許容） |
| dynamic import 対象 | 35 chunks |
| `/login` modulepreload | 20 件（entry closure + Login ページ closure、総ページ数非依存） |
| 最大 chunk | echarts を含む共有グラフ chunk 約 499KB（dynamic のみ到達） |

予算はこの実測値に約 20% の余裕を加えて [scripts/asset-budget.json](../../scripts/asset-budget.json) に設定している。予算を引き上げる場合は、実測の根拠を本ドキュメントへ追記する。

## 非目標

- chunk を 1 ファイルへまとめること
- manifest 総 chunk 数を減らすこと
- nginx 等の proxy buffer を増やして回避すること
- 独自 preload middleware を作ること
- 根拠なく複数 Vite entry へ分割すること
- 全ページを eager import すること
- dynamic chunk 数を少なく見せるためだけの統合

### 別 entry 化の判断基準

ClearDawn / ヨユウ / キオクの Vite entry 分割は現段階では行わない。以下がすべて実測で確認された場合のみ提案する。

- 別ドメイン・別 Blade・別ログインとして動作する
- プロダクト間移動でフルリロードしてよい
- dynamic import だけでは初期 bundle を十分に分離できない
- 共通依存の重複より entry 分割のメリットが大きい

## ガード（自動チェック）

| ガード | 場所 | 検知する退行 |
|---|---|---|
| Link ヘッダー ≤4KB / middleware 未登録 / Set-Cookie 維持 | [tests/Feature/LoginPageHeadersTest.php](../../tests/Feature/LoginPageHeadersTest.php) | FR-ASSET-004 |
| eager glob 禁止 / `lazy: false` 禁止 / `app.ts` のページ・プロダクト static import 禁止 | [tests/JavaScript/frontendAssetBoundariesGuard.test.mjs](../../tests/JavaScript/frontendAssetBoundariesGuard.test.mjs) | FR-ASSET-001/002/003 |
| static closure の chunk 数・bytes・gzip 予算、禁止 chunk パターン | `npm run assets:check`（要 `npm run build`） | FR-ASSET-002/003/005 |
| チェッカー自体のロジック | [tests/JavaScript/viteAssetBudget.test.mjs](../../tests/JavaScript/viteAssetBudget.test.mjs) | — |

### 実行方法

```bash
npm run build        # production build（manifest 生成）
npm run assets:check # static closure 予算チェック
npm run test:js      # 静的ガード + チェッカーのユニットテスト
php artisan test --compact tests/Feature/LoginPageHeadersTest.php
```

CI を整備する場合は、production build の直後に `npm run assets:check` を実行する。

## 新しい画面・プロダクトを追加するときのルール

1. ページは `resources/js/pages/<Product>/**` へ置くだけでよい。resolver が自動で遅延解決する。
2. プロダクト固有の重いライブラリ（音声処理、動画プレイヤー、グラフ、エディタ等）は、そのプロダクトのページまたはコンポーネントから import する。`app.ts`・レイアウト・OS シェルから import しない。
3. OS シェルから新プロダクトへ導線を張るときは、route ヘルパーと type-only import のみを使う。
4. `export *` の barrel file でプロダクトをまたいで再輸出しない。
5. 追加後に `npm run build && npm run assets:check` が通ることを確認する。予算超過は「初期 closure に何かが漏れた」シグナルであり、予算の引き上げではなく import 経路の修正で対処する。
