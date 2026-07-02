# Skills インデックス（汎用テンプレート）

各スキルは `<スキル名>/SKILL.md` に配置し、frontmatter（name / description）を必須とする。
これにより Cursor がスキルとして自動認識し、タスク種別に応じて自動で読み込まれる。

## 構成

| スキル | 種別 | 用途 |
|---|---|---|
| `bugfix/` | 思考フレームワーク | バグ修正（最小差分・確信度80%ルール） |
| `incident/` | 思考フレームワーク | 障害対応（原因候補P1〜P3・ログ起点） |
| `perf-review/` | 思考フレームワーク | パフォーマンスレビュー（提案のみ） |
| `review-only/` | 思考フレームワーク | コードレビュー（No Patch / No Apply） |
| `spec/` | 思考フレームワーク | 仕様検討・設計判断（論点分解・A/B比較） |
| `test-design-review/` | 思考フレームワーク | テスト設計レビュー（握りつぶし禁止） |
| `vue-sfc-patterns/` | 実装パターン | Vue 3 SFC・Inertia・Vitest（要プロジェクト調整） |
| `_shared/` | 共有リファレンス | 全スキル共通の概念定義（スキルではない） |

## 共通概念の定義場所

「地雷チェック」「確信度」「Data Cardinality」「Filter Location」「Ops Delta」「SEARCH_SCOPE の規律」は
[`_shared/analysis-concepts.md`](_shared/analysis-concepts.md) に一元定義されている。
各スキルはこれを参照し、**定義を重複記載しない**（正は1箇所、他は参照）。

## Rules との役割分担

| 場所 | 役割 |
|---|---|
| `.cursor/rules/*.mdc` | 常時適用される品質基準・禁止事項（alwaysApply） |
| `.cursor/skills/*/SKILL.md` | タスク種別ごとの手順・出力フォーマット（該当タスク時に読む） |

タスク種別 → スキルの対応表は `rules/task-skill-routing.mdc` に定義されている。

## スキルを追加するとき

1. `<スキル名>/SKILL.md` を作成し、frontmatter に `name` と `description`（いつ使うかを具体的に）を書く
2. 共通概念が必要なら `_shared/analysis-concepts.md` を参照する（再定義しない）
3. `rules/task-skill-routing.mdc` の対応表に行を追加する
4. この README の一覧に追加する
