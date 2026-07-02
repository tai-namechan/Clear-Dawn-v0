# ADR-0005: Query / Service / Eloquent レイヤリング（Repository 不採用）

- 状態: 承認済み

## 文脈

Clear Dawn v0 は個人向けセルフマネジメントアプリであり、Phase 1（TOP Matrix）から
Finance / 動画 / AI / Export API まで段階的に拡張する。
レイヤー構造を統一しないと、Controller 肥大化・AI Agent の実装ブレ・テスト戦略の不一致が起きる。

Repository Pattern を全領域に導入する案もあるが、現時点のコードベースには Repository が未導入であり、
Laravel の Eloquent を信頼する Clear Dawn の基本方針（architecture-layers.mdc）とも整合する
Query / Service 分離で十分である。

## 決定

以下のレイヤー構造を採用する。**Repository Pattern は現時点では導入しない。**

```text
Controller
  → FormRequest / Policy
  → Query（表示取得）または Service（更新）
  → Eloquent
```

### 各レイヤーの責務

| レイヤー | 責務 |
|---|---|
| **Controller** | HTTP 入口、Policy による認可、FormRequest による入力確定、Query / Service への委譲、Inertia 返却 |
| **FormRequest** | 入力バリデーション |
| **Policy** | 認可（自分のデータのみ操作可能） |
| **Query** | 読み取り専用の取得・集約・表示用データ構築。更新処理を含まない |
| **Service** | 更新系の業務ルール、複数モデル更新、必要な `DB::transaction` |
| **Model** | Relation、Cast、Scope、モデル固有の軽い振る舞い |
| **Repository** | **現時点では導入しない** |
| **外部依存** | AI プロバイダ、Object Storage、外部 API は **専用 Client クラス** で包む（汎用 Repository ではない） |
| **Export** | **ExportResource / DTO による allowlist** で出力（[export-api.md](../api/export-api.md)） |

### 読み取りと更新の分離

- 表示・一覧・詳細 → **Query**
- 作成・更新・削除・完了切替 → **Service**
- Controller に更新ロジックを肥大化させない

## 理由

- Laravel 標準（Eloquent）を信頼し、不必要な抽象化（Repository）を避ける
- test-quality-standards.mdc の「Query = Feature（実 DB）、Service = Unit（Mockery）」と一致する
- Phase 1 設計書（GetMatrixBoardQuery + 各 Service）および既存 Controller + FormRequest パターンと整合する
- AI Agent が一貫した型（Controller → Query/Service → Eloquent）で縦断実装できる

## 影響

- 新規領域の実装は Query / Service を作成する。Repository インターフェースは作らない
- 外部依存（M6 動画 / M7 AI）は `App\Services\...` または `App\Integrations\...` 等の
  専用 Client クラスで包む（命名は実装時に既存パターンに合わせる）
- Export（M8）は ExportResource / DTO の allowlist 方式とする
- 将来、特定領域で Repository が必要になった場合は、その領域に限り ADR を追加して導入する

## 関連

- [ADR-0001](./0001-ulid-primary-keys.md) — 主キー / FK 型
- [ADR-0002](./0002-user-scoped-single-user-domain.md) — user_id スコープ
- [export-api.md](../api/export-api.md) — Export allowlist 契約
- [top-matrix.md](../product/screens/top-matrix.md) — Phase 1 縦断設計の具体例
