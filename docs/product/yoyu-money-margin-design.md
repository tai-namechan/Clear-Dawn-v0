# ヨユウ「お金の余裕」— プロダクト設計・実装計画

- 作成日: 2026-07-17
- 調査基準: `tai-namechan/Clear-Dawn-v0` `main@62cac501289e956cd00da64e59b986f5d0b450af`
- 状態: **実装中（YM1〜YM7 + UI再設計）**
- ADR: [ADR-0010](../adr/0010-yoyu-money-margin-domain.md)
- 置換対象: [旧 Finance 仕様](./screens/finance.md)

> 本書に実在の口座名、金融会社名、残高、借入額、カード番号、CSV 内容は記載しない。
> 画面例・テスト値はすべて架空値を使用する。

## 0. 結論

「お金の余裕」は **ヨユウへ統合する**。別プロジェクトにも Clear Dawn 直下の家計簿にもせず、
既存 Laravel モノリス内の `Yoyu/Money` サブドメインとして実装する。

中心価値は取引記録ではなく、次の問いへ事実と選択肢で答えることである。

- 支払い後にいくら残るか
- 今月、安全に使える金額はいくらか
- いつ不足するか
- 何を止める・延期する・完済すると毎月の余裕がどう変わるか
- 今月の負担を下げたとき、将来負担と総支払額がどれだけ増えるか
- 実際に選んだ判断は想定どおりだったか

MVP は複式簿記ではなく、**残高事実・予定キャッシュフロー・実取引・照合**を分ける。
手入力と汎用 CSV マッピングを入力の中心とし、金融 API・Gmail・AI 分類は後続とする。

## 1. 配置

| 層 | 配置 |
|---|---|
| Backend | `app/Domain/Yoyu/Money/{Data,Models,Services,Support,Jobs,Enums}` |
| HTTP | `app/Http/Controllers/Yoyu/Money`、`Requests/Yoyu/Money` |
| Routes | `routes/yoyu.php` の `/yoyu/money/*`（`auth` + `verified`） |
| Frontend | `resources/js/pages/Yoyu/Money/*`、`components/yoyu-money/` |
| Layout | 既存 `OsShellLayout` / `OsSidebar`（Money ナビ追加） |
| Tests | `tests/{Feature,Unit}/Yoyu/Money/` |

## 2. MVP スコープ（YM1〜YM7）

1. Money 初期設定（タイムゾーン、JPY、安全資金、最低生活費）
2. 資金口座と残高履歴
3. カテゴリと支払先・収入元
4. 収入・支払予定の手入力と定期生成
5. 支払済み・入金済み、実取引、予定との照合
6. 月次ダッシュボードと資金繰り投影
7. クレジットカード、請求、未確定利用
8. ローン・負債、返済履歴、元金/利息の任意内訳
9. 汎用 CSV マッピング、preview、取込、重複候補、取消
10. 支出分析
11. 余裕シミュレーター
12. 意思決定履歴と振り返り
13. Export / 退会時ファイル cleanup

対象外: 金融 API、Gmail 解析、AI 助言、複数通貨合算、複式簿記、税務。

## 3. 不変条件

1. カード利用可能枠と借入可能額を保有資金へ加算しない
2. 金額本体は浮動小数で保存・計算しない（`BIGINT` minor + HTTP 文字列）
3. 同じ予定は一度だけ生成し、同じ取込行は一度だけ取引化する
4. 実取引は予定と照合しても削除・上書きせず、状態とリンクで表す
5. 取込取消は監査可能な `voided` とし、黙って物理削除しない
6. シミュレーションは適用前に実データを変更しない
7. 金融データは既存 AI コンテキストへ暗黙投入しない

## 4. 余裕額の式

```text
projected_cash_balance = F + Ic - Oc - Oe
projected_margin       = projected_cash_balance - L - S
safe_to_spend          = max(0, projected_margin)
shortfall              = max(0, -projected_margin)
```

- `F`: 資金口座の available（なければ current）合計。カード枠除外
- `Ic` / `Oc`: 未 settled の confirmed 収入/支出残額
- `Oe`: expected 支出 × 未確定予約率（既定 100%）
- `L`: 残り最低生活費（essential の二重予約を避ける）
- `S`: 安全資金（期間をまたいでも 1 回）

生活費/安全資金が null のときは `is_complete=false` とし、「安全に使える」と断定しない。

### 4.1 当日期日キャッシュフローの扱い

余裕額を**保守的（安全側）に表示**するため、当日（`asOf`）期日のキャッシュフローは非対称に扱う:

- **収入**: 当日期日は**除外**（`dueOn > asOf`）。入金時刻が不確実なため、入金を当てにした余裕を見せない
- **支出**: 当日期日・期限超過は**算入**（`dueOn <= horizonEnd`）。支払い義務は発生時点で余裕を圧迫する

この非対称により、余裕額は実際より少なめに表示される。ユーザーが「安全に使える」と判断したとき、想定外の不足が起きにくい設計意図による。

## 5. カード二重計上防止

| 目的 | 数えるもの | 数えないもの |
|---|---|---|
| 支出分析 | purchase / fee / interest（refund は減算） | card_payment、statement cashflow |
| 資金繰り | closed statement の cashflow | statement 期間内の個別 purchase |
| 未確定 | card snapshot の unconfirmed | snapshot と pending の二重加算 |

## 6. 画面情報設計（UI再設計）

最上位ナビゲーションは機能別タブではなく、次の5分類とする。

| 最上位 | 含む画面 | 既存URL（互換） |
|---|---|---|
| ホーム | 余裕ダッシュボード、残高タイムライン、調整候補 | `GET /yoyu/money` |
| 今月 | 収入・支払予定、支払後残高、処理済み操作 | `GET /yoyu/money/cashflows` |
| 資産・返済 | 口座 / カード / ローン | `/accounts` `/cards` `/loans` |
| 明細 | 取引明細 / CSV取込 / 取込履歴 | `/transactions` `/imports` |
| 計画 | 分析 / シミュレーター / 見直したこと | `/analysis` `/simulations` `/decisions` |

設定は歯車アイコン（`GET /yoyu/money/settings`）で独立操作とする。
既存URLは削除せず、対応する最上位ナビと内部タブが選択された状態で表示する。

### 6.1 ホームの表示優先順位

1. 安全に使える金額（未設定時は断定しない）
2. 月末予測・次の入金
3. 現在の資金 / 今月の収入 / 支払い予定
4. 今後の残高（イベント後残高付き）
5. もうすぐ支払うもの / 今月の注意点
6. 余裕を増やせる候補（ルールベース。無ければ「見直し候補はありません」）

カード利用可能枠・借入可能額は「信用枠情報」として分離し、現在の資金へ加算しない。

### 6.2 初回セットアップ

DBに完了フラグを持たず、既存データから進捗を算出する。

1. 口座と現在残高
2. 次の収入予定
3. 今月の支払い
4. カード・ローン（任意）
5. 最低生活費・安全資金

未完了でも各画面は閲覧可能。「あとで設定」はクライアント側で一時非表示できる。

### 6.3 二重計上防止のUI表現

取引明細の `card_payment` 等は「カード請求に含まれるため参考表示」と表示し、支出集計済みと区別する。

### 6.4 シミュレーションと実データ

シミュレーションの保存・計算だけでは実データを変更しない。反映は明示的な apply 操作のみ。

旧 `/finance` は認証後 `/yoyu/money` へ redirect する。

## 7. 主要受け入れシナリオ

| ID | Then |
|---|---|
| AC-01 | F10万 + Ic5万 − Oc8万 − L2万 − S1万 → cash7万、margin/safe4万 |
| AC-02 | Oc13万 → margin −1万、safe0、shortfall1万 |
| AC-03 | カード purchase / statement / 銀行引落は各 view で1回のみ |
| AC-05 | 毎月31日 rule の2月は末日1件、再実行しても1件 |
| AC-06 | 同一 CSV/target/mapping の再実行で transaction 増殖なし |
| AC-10 | simulation fingerprint 不一致の apply は stale |
| AC-12 | 他ユーザー ID は常に 404 |
| AC-14 | 生活費/安全資金未設定では断定ラベルを出さない |
| AC-15 | Money 明細を briefing/chat に暗黙投入しない |

詳細なテーブル定義・状態遷移・CSV/シミュレーション設計は ADR-0010 と本実装（`app/Domain/Yoyu/Money`）を正とする。
