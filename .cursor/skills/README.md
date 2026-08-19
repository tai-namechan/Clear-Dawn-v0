# Clear Dawn Cursor Skills

プロジェクト固有の思考フレームワーク。タスク種別に応じて `.cursor/rules/task-skill-routing.mdc` が参照する。

> **このディレクトリが手書きワークフロースキルの正（source of truth）である。**
> `.claude/skills/` からは symlink されており、Claude Code / Codex からも同じ内容が参照される。
> 編集はこちら（`.cursor/skills/`）に対して行うこと。

## プロジェクト固有スキル

| スキル | 種別 | 用途 |
|---|---|---|
| `bugfix/` | 思考フレームワーク | バグ修正（最小差分・確信度80%ルール） |
| `incident/` | 思考フレームワーク | 障害対応（原因候補P1〜P3・ログ起点） |
| `perf-review/` | 思考フレームワーク | パフォーマンスレビュー（提案のみ） |
| `review-only/` | 思考フレームワーク | コードレビュー（No Patch / No Apply） |
| `spec/` | 思考フレームワーク | 仕様検討・設計判断（論点分解・A/B比較） |
| `test-design-review/` | 思考フレームワーク | テスト設計レビュー（握りつぶし禁止） |
| `vue-sfc-patterns/` | 実装パターン | Vue 3 SFC・Inertia・Vitest |
| `_shared/` | 共有リファレンス | 全スキル共通の概念定義（スキルではない） |

共通概念は [`_shared/analysis-concepts.md`](_shared/analysis-concepts.md)。定義の正は1箇所。

## Laravel Boost 由来スキル

`php artisan boost:install` / `boost:update` で同期される。プロジェクト固有スキルと **同居** する（同名衝突に注意）。

| スキル | 用途 |
|---|---|
| `laravel-best-practices` | Laravel 実装のベストプラクティス |
| `fortify-development` | Fortify |
| `wayfinder-development` | Wayfinder |
| `tailwindcss-development` | Tailwind CSS |
| `deploying-laravel-cloud` | Laravel Cloud デプロイ（`.ai/skills` への symlink） |

詳細は `docs/dev/laravel-boost.md`。

## Rules との役割分担

| 場所 | 役割 |
|---|---|
| `.cursor/rules/*.mdc` | 常時適用される品質基準・禁止事項（alwaysApply） |
| `.cursor/skills/*/SKILL.md` | タスク種別ごとの手順・出力フォーマット |

タスク種別 → スキルの対応表は `rules/task-skill-routing.mdc`。

**ルールの正も `.cursor/rules/` である。** `CLAUDE.md` / `AGENTS.md` はそこを参照するだけで、
規約の本文を持たない。両ファイルの共通プリアンブルは CI で同一性が検査される。

## ディレクトリ構成の意図

| 分類 | 正の場所 | 同期方法 | 手編集 |
|---|---|---|---|
| 手書きワークフロースキル（7件 + `_shared`） | **`.cursor/skills/`** | `.claude/skills/` から symlink | 可（ここを編集） |
| Cloud デプロイスキル | `.ai/skills/deploying-laravel-cloud/` | 両者から symlink | 可 |
| Boost 生成スキル（7件） | `.claude/skills/` `.cursor/skills/` | `boost:update` が再生成 | **禁止** |

Boost 生成スキルを symlink 化してはいけない。`composer update` の `post-update-cmd` で
`boost:update` が走り、実体ファイルとして書き戻されるため。**生成器と戦わない。**
