# Laravel Boost（開発用 AI ツール）

> パッケージ: `laravel/boost`（require-dev）  
> 公式: https://laravel.com/docs/boost

## 導入構成

| 成果物 | パス | 役割 |
|---|---|---|
| Composer 依存 | `composer.json` (`laravel/boost`) | MCP サーバ本体 |
| Boost 設定 | `boost.json` | agents / guidelines / skills / mcp |
| MCP | `.mcp.json` | `php artisan boost:mcp`（Claude Code） |
| AI Guidelines | `CLAUDE.md` | Boost ガイドライン + プロジェクト優先順位 |

エージェントは **Claude Code**（`claude_code`）を選択済み。

## プロダクト仕様との関係

**プロダクト仕様・データ設計の正は `docs/`**。Boost ガイドラインと矛盾する場合は `docs/` を優先する（`CLAUDE.md` 冒頭にも明記）。

## 主な MCP ツール

| ツール | 用途 |
|---|---|
| search-docs | インストール版の Laravel / Inertia ドキュメント |
| database-schema | マイグレーション後のスキーマ確認 |
| tinker | 集計クエリの実データ検証 |
| last-error / ログ | テスト失敗・500 の原因特定 |

## セットアップ

```bash
composer install
php artisan boost:install --guidelines --skills --mcp --no-interaction
# または
php artisan boost:update
```
