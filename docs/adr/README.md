# ADR（Architecture Decision Records）

設計・技術に関する確定した意思決定を記録する。
[roadmap.md](../roadmap.md) の未決定事項が確定するたびに、連番で追加する。

## 一覧

| # | タイトル | 状態 |
|---|---|---|
| [0001](./0001-ulid-primary-keys.md) | 主キーに ULID を採用する | 承認済み |
| [0002](./0002-user-scoped-single-user-domain.md) | user_id スコープ・シングルユーザードメインとする | 承認済み |
| [0003](./0003-single-top-matrix-without-history.md) | TOP Matrix はユーザーごと 1 つ・履歴を持たない | 承認済み |
| [0004](./0004-ui-built-with-html-css-vue.md) | UI は画像一枚に頼らず HTML/CSS/Vue で構成する | 承認済み |

## フォーマット

各 ADR は以下の構成で書く。

- **状態**: 提案 / 承認済み / 廃止（廃止時は後継 ADR を記す）
- **文脈**: なぜこの判断が必要になったか
- **決定**: 何を決めたか
- **理由**: 決定の根拠
- **影響**: この決定が及ぼす制約・波及
