# Fable5 向けプロンプト — PR-F2 成分表OCR 実装設計の詰め

以下をそのまま Fable5 に貼り付けて使う。

---

## プロンプト本文（ここからコピー）

```text
あなたは Clear Dawn（Laravel 13 + Inertia/Vue3 + TypeScript）の実装設計担当です。
今回の依頼は **実装ではなく、まず PR-F2（成分表OCR）の実装設計を詰め切ること** です。
設計が固まったら実装に入ってよいですが、必ずフェーズ分割・都度コミット/プッシュ・残作業ファイル出力の運用を守ってください。

================================================================================
0. 目的
================================================================================

PR-F1（バーコード → Open Food Facts）は実装済み（またはマージ間近）。
PR-F2 は「F1 が miss のとき、栄養成分表示を撮影 → AI OCR → ユーザー確認 → food_items 保存」を実装する。

「何をするか」は設計確定済み。あなたが詰めるのは **どう実装するか**（API・Job・検証・UI・テスト・課金連携・画像ライフサイクル）です。

================================================================================
1. 必読ドキュメント（この順で読む）
================================================================================

1. docs/design/ai-features-completion-design.md §3
   （全体フロー・やらないこと・原価感・写真破棄）
2. docs/design/ai-features-implementation-plan.md
   - §2.11（外部通信は Queue）
   - §13 全体（F1/F2）
   - 特に §13.4 PR-F2 の 9 手順
3. docs/dev/2026-07-20-pr-f1-handoff-for-cursor.md
   （F1 の現状・共有できる受け皿・注意点）
4. 実装の正として既存コードを Read する（推測禁止）:
   - app/Services/StartFoodBarcodeLookupService.php
   - app/Services/ConfirmFoodLookupService.php
   - app/Jobs/LookupOpenFoodFactsJob.php
   - app/Http/Controllers/FoodBarcodeLookupController.php
   - app/Models/FoodLookupRequest.php
   - database/migrations/2026_07_19_100001_add_barcode_to_food_items_and_create_food_lookup_requests.php
     （temp_image_path が F2 用に既にある）
   - resources/js/components/BarcodeLookupModal.vue
   - app/Domain/Shared/AI/AiGateway.php
   - 既存の vision / transcription Job（課金・reserve/settle の類似実装）

docs とコードが食い違う場合は **コードを優先**し、差分を設計書に明記する。勝手に別設計へ進めない。

================================================================================
2. PR-F2 で確定している仕様（変えない）
================================================================================

フロー:
1. F1 miss（NotFound / Failed 含む。設計上「照合3」）→ 成分表カメラへ誘導
2. 栄養成分表示を撮影・upload
3. private temporary storage
4. file size / MIME / dimension 検証
5. OCR Job（Queue。画面同期通信禁止）
6. AiGateway feature=`meals.label_ocr`, tier=cheap（vision）
7. JSON schema validation
8. 確認フォーム（AI結果は自動確定しない）
9. ユーザー確定後だけ food_items 保存（barcode があれば紐づけ）
10. 成功・失敗・期限切れで画像削除

やらないこと:
- 商品DBの外部購入
- 料理写真からの推定
- Open Food Facts / OCR を Web リクエスト中に同期実行

プロンプト方針（完成設計 §3）:
「栄養成分表示から JSON {serving_label, kcal, protein_g, fat_g, carb_g, per:'serving|100g'} のみ」

================================================================================
3. 設計フェーズで必ず決める論点（抜け禁止）
================================================================================

以下を A/B 比較または推奨＋理由で確定し、実装計画書に書くこと。

A. エントリポイント
- F1 の BarcodeLookupModal から miss 時に F2 へ遷移するか、別モーダル/画面か
- barcode なし（成分表だけ撮る）を許すか。許すならルートは何か

B. データモデル
- food_lookup_requests を F2 でも使うか、別テーブルか
- status 拡張が必要か（例: awaiting_image / ocr_pending / ocr_failed）
- temp_image_path / expires_at / source / result / error_code の使い分け
- barcode 紐づけのタイミング（upload時 / confirm時）

C. ストレージ
- disk 名（private）、パス規約、TTL、削除トリガ（成功/失敗/期限切れ/ユーザー放棄）
- 画像をいつ破棄するか（解析後即時 vs confirm 後）— 完成設計は「解析後破棄（保存するなら明示同意）」とあるので整合を取る

D. Upload API
- エンドポイント・認可・バリデーション（MIME/size/dimension の具体値）
- FormRequest / Policy / user_id スコープ
- 既存 lookup_id に紐づけるか、新規 request を作るか

E. OCR Job
- クラス名、Queue 名（dedicated or default）、tries/backoff/timeout、ShouldBeUnique
- AiGateway 呼び出し契約（feature / tier / 入力画像の渡し方）
- プロンプト本文・JSON schema・失敗時 error_code
- リトライ可能な失敗 vs 即 Failed

F. 課金・利用量
- AiGateway / ledger への自動連携（PR-A）をどう繋ぐか
- 上限超過時の UX（422? 画面メッセージ?）
- テストで外部AIを呼ばない方法（fake / fake gateway）

G. フロント
- 撮影 UI（input capture / getUserMedia）
- polling 再利用（F1 と同じ境界か）
- confirm フォーム（per serving / 100g 明示、出典表示）
- miss → OCR 導線の文言

H. テスト
- Feature / Unit / Job で何を証明するか（成功・不正画像・他ユーザー403/404・期限切れ削除・自動確定しないこと）
- Http::preventStrayRequests / AI stray 禁止の守り方

I. 非目標（本PRでやらないこと）の再確認リスト

================================================================================
4. 成果物（設計フェーズ完了条件）
================================================================================

設計フェーズが終わったら、**実装に入る前に** 必ず次を行う:

1. 実装計画書をリポジトリに Markdown で作成・コミット・プッシュする
   - 推奨パス: docs/dev/YYYY-MM-DD-pr-f2-label-ocr-implementation-plan.md
2. 同内容の要約をチャットにも出す（ただし正本はファイル）
3. 計画書に必ず含める見出し:
   - 概要 / 非目標
   - 現状（F1からの再利用点）
   - 決定事項（上記 A〜I）
   - フェーズ分割（後述）
   - 変更ファイル一覧（予定）
   - API 契約（リクエスト/レスポンス例）
   - DB/ストレージ
   - Job / AiGateway 契約
   - フロント画面遷移
   - テスト計画
   - 受入条件（Given/When/Then）
   - リスクと未決事項（あればオーナー判断が必要な1行質問）
4. 未決でオーナー判断が必要なことは、勝手に決めず質問を1〜3個に絞る

確信度が低い事項は断定しない。

================================================================================
5. 実装フェーズの運用（リミット対策・必須）
================================================================================

設計承認後（またはオーナーが「実装して」と言った後）のみ実装する。

### 5.1 フェーズ分割（推奨。必要なら調整してよいが分割は必須）

- Phase 0: 設計書ファイルの作成・push（実装なし）
- Phase 1: DB/Enum/Model の必要差分 + テスト骨格
- Phase 2: Upload API + ストレージ検証 + Feature テスト
- Phase 3: OCR Job + AiGateway 連携（fake）+ Job テスト
- Phase 4: 確認フロー接続（既存 Confirm の拡張 or 新API）+ Feature テスト
- Phase 5: フロント（miss導線・撮影・polling・confirm）
- Phase 6: 画像削除（成功/失敗/期限切れ）・掃除コマンド or 既存機構 + テスト
- Phase 7: Lint/Pint/回帰・docs更新・draft PR

各 Phase は「1コミットでレビュー可能な最小単位」にする。大きくしすぎない。

### 5.2 毎フェーズ終了時の必須アクション

各 Phase の作業が終わるたびに、必ずこの順で行う:

1. テストをその Phase の範囲で実行し、結果を記録
2. `vendor/bin/pint --dirty --format agent`（PHP変更時）
3. git add / commit（わかりやすいメッセージ）
4. git push -u origin <branch>
5. **残作業ファイルを更新して commit & push**
   - 推奨パス: docs/dev/YYYY-MM-DD-pr-f2-label-ocr-handoff.md
   - 毎回上書き更新でよい。最低限含める項目:
     - ブランチ名 / 最新コミット
     - 完了した Phase
     - 残 Phase 一覧（チェックボックス）
     - 次にやる具体タスク（ファイル名つき）
     - 詰まった点 / 未決事項
     - ローカル検証コマンド

リミットや中断に備え、「残作業ファイルだけで別エージェントが再開できる」粒度で書くこと。
チャットだけの進捗報告で終わらせないこと。

### 5.3 Git / PR

- base: main（またはオーナー指定）
- ブランチ名例: cursor/meals-label-ocr-f2-<suffix> または feature/meals-label-ocr-f2
- draft PR を早期に作り、Phase ごとに更新してよい
- force push / main 直 push / 無関係リファクタ禁止
- 共通サイドバー等の無関係変更禁止

================================================================================
6. コーディング規約（実装時）
================================================================================

- Laravel / 既存 F1 パターン踏襲（Service / FormRequest / Job / Feature test）
- 画面リクエスト中に外部AI・OFFへ同期通信しない
- AI結果の自動確定禁止
- 他ユーザーの lookup / 画像に触れない（Feature テストで証明）
- テストで外部HTTPを実送しない（Http::preventStrayRequests / fake）
- 差分最小。docs は依頼された計画/handoff 以外を勝手に増やさない
- パッケージ追加（npm/composer）が必要なら実装前に理由を書いて承認を取る

================================================================================
7. 最初の応答でやること
================================================================================

1. 必読ドキュメントと F1 コードを読んだうえで、設計の不足点を列挙
2. 論点 A〜I の推奨案を提示（まだファイルを確定書き込みしてよい）
3. 実装計画 Markdown を作成し、commit & push
4. 残作業 handoff ファイルを作成し、Phase 0 完了・次は Phase 1 と明記して push
5. オーナー判断が必要な質問があれば最大3つ

「実装計画ファイルを書いて push するまで」が最初のゴールです。
そこから先の実装は、オーナーが続行を指示してから Phase 1 へ進んでください。
（ただしオーナーが最初から「設計してそのまま実装まで」と明示している場合は、計画 push 後に Phase 1 へ進んでよい。その場合も Phase ごとに commit/push/handoff 更新を省略しないこと。）

以上。
```

## プロンプト本文（ここまで）

---

## 使い方メモ（人間向け）

1. 上の「プロンプト本文」を Fable5 に貼る
2. 最初は **設計書 + handoff の push まで** で止めたい場合、最後に一言足す:
   - `今回は Phase 0（設計書作成）まで。実装は次の指示を待て。`
3. 通しで進めたい場合:
   - `設計を push したら、続けて Phase 1 から実装してよい。各 Phase で commit/push/handoff 更新を忘れるな。`
4. 参照実装の正本:
   - F1: PR #143 / ブランチ `cursor/meals-barcode-lookup-a703`
   - 設計: `docs/design/ai-features-completion-design.md` §3
   - 実装計画骨格: `docs/design/ai-features-implementation-plan.md` §13.4
