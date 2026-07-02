# デザインシステム

UI 仕様書 v2 の内容を正として移植したドキュメント。
実装時のデザイントークンの正は `resources/css/app.css` とする（design-consistency.mdc 準拠）。

## 1. UI コンセプト

> 夜明け前の静けさの中で、今日やるべきことを決める。

基本方針: **外側は美しく、内側は読みやすく。**
背景とサイドバーで世界観を作り、シートとボタンで行動を明確にする。
トップページの主役は背景ではなく、中央のマトリクスシート。
業務システムの表ではなく、上品な紙の整理シートとして仕上げる。

## 2. 目指す品質

| NG 状態 | OK 状態 |
|---|---|
| 業務システムの表に見える | シートが主役として自然に目に入る |
| Bootstrap を少し装飾しただけに見える | 背景は上品だが主張しすぎない |
| 素材を配置しただけに見える | サイドバーは夜明け前の世界観として馴染んでいる |
| サイドバーが画像っぽく浮いている | タイトルにクラシック感・余白・品がある |
| 罫線が強く、Excel 感がある | テーブルは表ではなく、紙の整理シートに見える |
| タイトルフォントが太く、ブランド感が弱い | 罫線・影・文字色が柔らかい |
| アイコンが文字化け・記号っぽく見える | アイコン・月・植物線画が統一された線幅で見える |

## 3. レイアウト仕様

| 項目 | 仕様 |
|---|---|
| 画面全体 | 左サイドバー + メインコンテンツ。PC 16:9（1920x1080）を主軸 |
| 左サイドバー | CD ロゴ、月、ナビゲーション、星、植物線画。幅 168px |
| メインコンテンツ | Clear Dawn タイトル、装飾ライン、日付、マトリクスシート |
| レイアウト方針 | サイドバーは世界観とナビゲーションを担当し、メインはシートへの集中を優先する |

## 4. 背景仕様

薄い白大理石系の画像を使用する。画像をそのまま強く出さず、
白〜生成りのオーバーレイを重ね、中央のシートを主役にする。

| 区分 | 条件 |
|---|---|
| OK | 白〜生成り系、薄い大理石、模様が控えめ、中央が明るい、シートの文字を邪魔しない |
| NG | グレーの筋が強すぎる、金色が強すぎる、高級ホテル感・美容サロン感が強すぎる、背景が主役になる |

```css
.app-layout {
    position: relative;
    min-height: 100vh;
    background-color: #f7f3ec;
    background-image: url('/images/backgrounds/paper-marble-mist.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    color: var(--cd-text);
}
.app-layout::before {
    content: '';
    position: fixed;
    inset: 0;
    background: rgba(255, 252, 248, 0.52);
    pointer-events: none;
    z-index: 0;
}
```

## 5. サイドバー仕様

サイドバーは画像一枚で作らない。CSS グラデーション + SVG 装飾で構成する。
深紫の縦グラデーション、星の粒子、月の SVG、下部の植物線画、うっすらしたノイズ・質感、
装飾とナビの重なり制御を組み合わせる。

```css
.sidebar {
    width: 168px;
    min-height: 100vh;
    position: relative;
    overflow: hidden;
    color: #ffffff;
    background:
        radial-gradient(circle at 45% 12%, rgba(255, 255, 255, 0.15), transparent 22%),
        linear-gradient(180deg, #15162d 0%, #232442 38%, #4a456f 76%, #8a83a5 100%);
}
.sidebar::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        radial-gradient(circle, rgba(255, 255, 255, 0.65) 1px, transparent 1.5px),
        radial-gradient(circle, rgba(255, 255, 255, 0.35) 1px, transparent 1.5px);
    background-size: 52px 52px, 88px 88px;
    opacity: 0.36;
    pointer-events: none;
    z-index: 1;
}
```

注意点:

- サイドバー全体を画像化しない
- 星・月・植物は装飾として重ねる
- ナビアイコンは必ず SVG で統一する。文字や絵文字を使わない
- 下部が明るくなりすぎる場合は暗いオーバーレイを足す

## 6. マトリクスシート仕様

中央のシートがトップページの主役。Excel の表ではなく、上品な紙のシートに見せる。
罫線はかなり薄く、影は柔らかく、背景は真っ白ではなく少し生成りにする。

| 項目 | 仕様 |
|---|---|
| 行 | 1 ヶ月くらいの間でやるべきこと / 今やるべきこと / 将来どうなっていたいか |
| 列 | ユーザー定義の領域（例: 仕事 / 野球 / バイオリン / プライベート） |

```css
.matrix-card {
    width: min(1280px, calc(100vw - 260px));
    margin: 0 auto;
    background: rgba(255, 252, 248, 0.88);
    border: 1px solid rgba(221, 216, 208, 0.72);
    border-radius: 20px;
    box-shadow:
        0 18px 48px rgba(31, 42, 61, 0.13),
        0 2px 8px rgba(31, 42, 61, 0.06);
    backdrop-filter: blur(8px);
    overflow: hidden;
}
.matrix-table th,
.matrix-table td {
    border: 1px solid rgba(205, 211, 220, 0.62);
    padding: 28px 32px;
    vertical-align: middle;
    text-align: center;
}
```

### 「今やるべきこと」行

画面内で最も行動につながる領域。ただし強調しすぎると世界観を壊すため、
淡い朝焼け色で柔らかく示す。

```css
.matrix-table tr.is-current {
    background: linear-gradient(90deg, rgba(253, 244, 236, 0.96), rgba(248, 232, 219, 0.78));
}
.matrix-current-label svg {
    width: 22px;
    height: 22px;
    color: var(--cd-accent);
    stroke-width: 1.6;
}
```

- 朝日のアイコンは絵文字を使わない。SVG（Lucide: Sunrise）を使う
- オレンジは強くしすぎない
- 行全体の背景は淡くする。罫線より背景の柔らかさを優先する

## 7. フォント仕様

品質差で最も重要なのはフォント。タイトルのフォントが太いと業務システム感が出る。

| 項目 | 仕様 |
|---|---|
| タイトル | 細めのセリフ体。文字間は広め。クラシック感を出す |
| 日本語見出し | 上品な明朝系 |
| 本文 | 可読性の高いゴシック。ただし太すぎない |
| タスク本文 | 手書き風を多用しない。実装では読みやすさを優先 |

```css
:root {
    --cd-font-title: 'Cormorant Garamond', 'Times New Roman', serif;
    --cd-font-serif-ja: 'Shippori Mincho', 'Noto Serif JP', serif;
    --cd-font-body: 'Zen Kaku Gothic New', 'Noto Sans JP', system-ui, sans-serif;
}
.page-title {
    font-family: var(--cd-font-title);
    font-size: 56px;
    font-weight: 400;
    letter-spacing: 0.08em;
    line-height: 1;
    color: var(--cd-primary);
}
```

- フォントの配信方法（セルフホスト or CDN）は **未決定**。プライバシーと表示安定性の観点から
  セルフホスト（`public/fonts/`）を推奨（M0 で確定・ADR 化する）

## 8. アイコン仕様

- アイコンは SVG で統一する（Lucide Icons を採用。`@lucide/vue` 導入済み）
- 絵文字やフォントアイコンは使わない
- 線幅を揃える（stroke-width 1.6 目安）
- `stroke="currentColor"` にする。塗りではなく線画ベース

| 用途 | アイコン |
|---|---|
| ダッシュボード | Home |
| メモ | Pencil |
| 振り返り | Notebook / CalendarCheck |
| 設定 | Settings |
| 日付 | Calendar |
| 今やるべきこと | Sunrise |
| 目標 | Target |
| 習慣・ルーティン | CircleCheck |
| 記録 | ChartLine |
| Finance | Wallet |
| 動画 | Clapperboard |
| AI 支援 | Sparkles |

## 9. カラー設計（デザイントークン）

```css
:root {
    --cd-primary: #252646;
    --cd-primary-dark: #17182f;
    --cd-primary-hover: #1b1c35;
    --cd-primary-light: #6e6a96;
    --cd-secondary: #6e6a96;
    --cd-secondary-soft: #c9c2d8;
    --cd-accent: #d98b5f;
    --cd-accent-hover: #c9794f;
    --cd-accent-soft: #f8e8db;
    --cd-accent-light: #fdf4ec;
    --cd-bg: #f7f3ec;
    --cd-bg-soft: #fbf7f1;
    --cd-surface: #fffcf8;
    --cd-surface-muted: #f3efe8;
    --cd-surface-glass: rgba(255, 252, 248, 0.86);
    --cd-text: #1f2a3d;
    --cd-text-muted: #6f7480;
    --cd-text-subtle: #9a9aa6;
    --cd-text-inverse: #ffffff;
    --cd-border: #ddd8d0;
    --cd-border-soft: #eae4dc;
    --cd-border-table: rgba(205, 211, 220, 0.62);
    --cd-success: #6f8f72;
    --cd-warning: #d9a441;
    --cd-danger: #b96a64;
    --cd-info: #6f8faf;
    --cd-shadow-sm: 0 2px 8px rgba(31, 42, 61, 0.08);
    --cd-shadow-md: 0 8px 24px rgba(31, 42, 61, 0.12);
    --cd-shadow-lg: 0 16px 40px rgba(31, 42, 61, 0.16);
    --cd-radius-sm: 8px;
    --cd-radius-md: 12px;
    --cd-radius-lg: 18px;
    --cd-radius-xl: 24px;
}
```

### Tailwind v4 / 既存トークンとの統合方針

- 既存 shadcn 系トークン（`--background` / `--foreground` / `--primary` 等）の **値** を
  Clear Dawn パレットで上書きし、既存の Tailwind ユーティリティ
  （`bg-background`, `text-foreground` 等）をそのまま活かす
- Clear Dawn 固有トークン（`--cd-accent-soft` 等、shadcn に対応先がないもの）は
  `app.css` の `@theme inline` に追加マッピングしてユーティリティ化する
- 既存の CSS クラス・トークン定義は削除しない（追加・値変更で対応）
- チャート配色は `--chart-1`〜`--chart-5` に cd パレット
  （primary / accent / success / info / warning）を割り当てる
- ダークモード: v0 は非対応を推奨（世界観が「夜明け前の紙」で固定のため）。
  既存 `.dark` トークンの扱いは **未決定**（M0 で確定）

## 10. ボタン仕様

| 用途 | クラス |
|---|---|
| 保存・登録・更新 | btn-primary |
| 今日を始める・トレーニング開始 | btn-accent |
| 編集・戻る・キャンセル | btn-secondary |
| 閉じる | btn-ghost |
| 削除 | btn-danger |

```css
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 40px;
    padding: 0 18px;
    border-radius: var(--cd-radius-md);
    border: 1px solid transparent;
    font-family: var(--cd-font-body);
    font-size: 14px;
    font-weight: 500;
    line-height: 1;
    cursor: pointer;
    transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        color 0.2s ease,
        box-shadow 0.2s ease,
        transform 0.1s ease;
}
.btn-primary {
    background: var(--cd-primary);
    color: var(--cd-text-inverse);
    border-color: var(--cd-primary);
}
.btn-accent {
    background: var(--cd-accent);
    color: var(--cd-text-inverse);
    border-color: var(--cd-accent);
}
```

実装方針: 既存 `resources/js/components/ui/button` の内部は改変せず、
CVA variant の追加またはラッパーコンポーネントで 5 系統に対応付ける。

## 11. 画像にしないもの

以下は必ず HTML/CSS/Vue コンポーネントで作る（[ADR-0004](../adr/0004-ui-built-with-html-css-vue.md)）。

- サイドバー全体 / テーブル全体 / ボタン / チェックボックス / 文字入り UI / カード / モーダル

理由: レスポンシブ対応しづらく、テキスト編集・状態管理・ホバー・クリック・メニュー追加に弱いため。

## 12. レスポンシブ方針

- 主軸: PC 16:9（1920x1080 / 1536x864）
- 実装はレスポンシブとするが、スマホ最適化（PWA）は後続フェーズ
- 中間幅でのサイドバー折りたたみ有無・最小対応幅は **未決定**（M0〜M1 で確定）

## 13. 最終方針

AI 生成画像のような「雰囲気の良さ」を目指すが、実装では画像一枚に頼らない。
背景・月・植物・星・装飾ラインは世界観を作る素材。
シート・文字・ボタン・ナビは Web UI として実装する。

最重要は、**フォント、余白、罫線、影、透明度、サイドバーの馴染ませ。**
業務システムの表ではなく、夜明け前の静けさの中で開く、上品な紙のマトリクスシートとして仕上げる。

実装時の確認項目は [ui-quality-checklist.md](./ui-quality-checklist.md) を参照。
