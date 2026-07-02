# 素材（アセット）管理ルール

UI 仕様書 v2「画像・素材仕様」を移植したもの。

## 役割分担

| 区分 | 内容 |
|---|---|
| AI に作らせるもの | 大理石背景、月、星、バイオリン線画、装飾ライン |
| 自分で調整するもの | 色味、透明度、サイズ、配置、SVG 化、余白削除、ファイル名、ディレクトリ管理、WebP 変換 |
| 自分で設計するもの | ロゴ、UI パーツ、ボタン、テーブル、サイドバー構造、デザイントークン、レスポンシブ、状態管理 |

## ディレクトリ構成

```text
public/images/
    backgrounds/
        paper-marble-mist.png
    decorations/
        moon-glow.png
        stars-soft.png
        title-ornament.svg
        sidebar-violin.svg
    logo/
        clear-dawn-mark.svg
        clear-dawn-wordmark.svg
        clear-dawn-logo.svg
    icons/
        nav-dashboard.svg
        nav-memo.svg
        nav-review.svg
        nav-settings.svg
        nav-goals.svg
        nav-habits.svg
```

M0 で配置済みの素材は上記 `backgrounds/` と `decorations/` 配下の 5 ファイル。
`logo/` と `icons/` は未配置（命名・配置方針の参照用）。

- ナビアイコンは原則 `@lucide/vue` コンポーネントを使用する。
  `icons/` 配下の SVG ファイルは Lucide にない独自アイコンが必要になった場合のみ追加する

## ルール

- ビットマップ画像（背景等）は WebP 変換版を用意し、実装では WebP を優先する
- 装飾ライン・サイドバー装飾（バイオリン）は SVG で管理し、`currentColor` / CSS 変数 / `mask-image` で着色できる形にする
- 月・星は M0 では半透明 PNG（`moon-glow.png` / `stars-soft.png`）で配置する
- ファイル名は kebab-case、内容が分かる命名にする
- 画像にしてよいのは「世界観素材」のみ。UI（サイドバー全体・テーブル・ボタン・カード・
  モーダル・文字入り UI）は画像化しない（[design-system.md](./design-system.md) 11. 参照）
- 素材はライセンス上問題のないもの（自作 / AI 生成 / 商用利用可）のみ使用する
