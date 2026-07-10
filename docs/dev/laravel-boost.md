# Laravel Boost（開発用 AI ツール）

> 最終更新: 2026-07-10  
> パッケージ: `laravel/boost`（require-dev）  
> 公式: https://laravel.com/docs/boost

## なぜ入れるか

Clear Dawn の実装スタイルは **Route → Controller → Query/Service → Vue → テスト** の縦断である。  
Boost は Laravel バックエンドを横断する実装中に、推測ではなく **実アプリ・実スキーマ・版正確なドキュメント** で確認するための MCP ツール群を提供する。

M4 以降は集計クエリ・金額計算・Object Storage・API 設計が厚くなるため、導入時期として適切。

## 導入済み構成

| 成果物 | パス | 役割 |
|---|---|---|
| Composer 依存 | `composer.json` (`laravel/boost`) | MCP サーバ本体 |
| Boost 設定 | `boost.json` | agents / guidelines / skills / mcp の選択状態 |
| Cursor MCP | `.cursor/mcp.json` | `php artisan boost:mcp`（Cursor） |
| Claude Code MCP | `.mcp.json` | `php artisan boost:mcp`（Claude Code） |
| AI Guidelines (Cursor) | `AGENTS.md` | インストール版に合わせた Laravel 規約 |
| AI Guidelines (Claude Code) | `CLAUDE.md` | Boost ガイドライン + プロジェクト優先順位 |
| Boost Skills (Cursor) | `.cursor/skills/{fortify,laravel-best-practices,tailwindcss,wayfinder,deploying-laravel-cloud}` | オンデマンド知識 |
| Boost Skills (Claude Code) | `.claude/skills/{fortify,laravel-best-practices,wayfinder,inertia-vue,tailwindcss,deploying-laravel-cloud}` | オンデマンド知識 |
| Cloud skill 本体 | `.ai/skills/deploying-laravel-cloud/` | Laravel Cloud 向け skill |

エージェントは **Claude Code**（`claude_code`）と **Cursor**（`cursor`）の両方を選択済み。

プロジェクト固有スキル（`bugfix`, `spec`, `vue-sfc-patterns` 等）は **そのまま共存** する。Boost は同名ディレクトリだけを同期し、既存スキルは消さない。

## プロダクト仕様との関係

**プロダクト仕様・データ設計の正は `docs/`**。Boost ガイドラインと矛盾する場合は `docs/` を優先する（`CLAUDE.md` 冒頭にも明記）。

## セットアップ（新規クローン時）

```bash
composer install
php artisan boost:install --guidelines --skills --mcp --no-interaction
# または既存 boost.json がある場合
php artisan boost:update
```

Cursor 側:

1. Command Palette → MCP Settings
2. `laravel-boost` を有効化

ローカル環境（`APP_ENV=local` または `APP_DEBUG=true`）でのみ Boost は有効。production では動かない。

## 主な MCP ツールと使いどころ

| ツール | 用途 | このプロジェクトでの効き目 |
|---|---|---|
| Database Schema | 現在のテーブル構造を直接確認 | migration 追跡なしで `metric_records` 等を把握 |
| Database Query / Tinker | 実データでクエリ・リレーション検証 | M4 週次平均、M5 金額集計の正しさ確認 |
| Search Docs | インストール版の公式ドキュメント検索 | M6 署名付き URL、Cloud Object Storage |
| Application Info | パッケージ版・モデル一覧 | 縦断実装の前提合わせ |
| Route 系 / Artisan | ルート・コマンド確認 | M8 Export API 設計 |
| Last Error / Read Log | 実際の例外・ログ | Feature テスト 500 の原因特定 |
| Browser Logs | ブラウザコンソール | フロント連携時の補助（主戦場は PHP） |

## ロードマップとの対応

| MS | Boost の恩恵 | 備考 |
|---|---|---|
| M1 / M3 | 低〜中（実装済） | activity_logs 不変条件の事後検証に Tinker が有用 |
| **M4 残り** | **高** | 集計クエリの正しさが肝。Tinker で実データ検証 |
| **M5** | **高** | 金額計算・集計 |
| **M6 仕上げ** | **高** | 学習データが薄い領域 → search-docs |
| M7 | 中 | プロバイダ確定後。docs 参照 |
| **M8** | **高** | 認証・API Resource・list-routes |
| 純粋 Vue / UI | 低 | Boost は PHP 側ツール |

進捗の現在地は [../progress.md](../progress.md) を参照。

## 更新

```bash
php artisan boost:update
```

`composer.json` の `post-update-cmd` に `boost:update` を入れてある場合は `composer update` 後に自動実行される。

## 注意

- Boost 生成物（`AGENTS.md`, `CLAUDE.md`, `boost.json`, `.cursor/mcp.json`, `.mcp.json`, Boost skills）はチームで共有するためリポジトリに含める
- `boost:install` を再実行すると guidelines / MCP 設定が上書きされる。プロジェクト固有スキル名と Boost skill 名が衝突しないよう注意
- Sail 未構築のため MCP コマンドはホストの `php artisan boost:mcp` を使う（現状の MCP 設定どおり）
