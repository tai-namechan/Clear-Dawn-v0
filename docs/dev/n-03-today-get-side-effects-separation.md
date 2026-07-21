# N-03: TodayController GET 副作用の POST 分離 設計チケット

起票: 2026-07-21
関連: [PR #136〜#138 レビュー](./2026-07-18-pr136-138-code-review.md) / [today-ops.md](../product/screens/today-ops.md)
前提: N-02（推奨カード ID 冪等化）により GET 副作用の毒性は大幅低減済み

## 現状の問題

`TodayController::index`（`GET /today`）が 2 つの書き込みサービスを同期呼び出ししている:

```
GET /today
  → GenerateProgramDayPlansService::handle()   ← DB書き込み
  → EvaluateRulesForDayService::handle()       ← DB書き込み
  → GetTodayQuery / GetTodayOpsQuery           ← 読み取りのみ
  → Inertia::render
```

### なぜ問題か

1. **HTTP 意味論違反**: GET はべき等・安全であるべき（RFC 9110 §9.2.1）。書き込みが GET に含まれると CDN/プリフェッチ/ブラウザの投機的リクエストで意図しない副作用が起きうる
2. **レスポンス遅延**: プラン生成とルール評価のトランザクションがレンダリングをブロックする。初回アクセス時に特に影響大
3. **テスト複雑度**: GET テストが暗黙の書き込みを前提にしている（テストが GET を呼ぶだけで plan/recommendation が生成される）
4. **他コントローラとの非対称**: `DailyCheckinController`（PUT）や `SymptomObservationController`（POST）は書き込みの結果として `evaluateRules` を呼ぶ（正しいパターン）。`TodayController` だけが読み取りリクエストで同じことをしている

### N-02 による緩和

N-02 で `EvaluateRulesForDayService` を冪等化したため:
- GET の再呼び出しで recommendation ID が変わらない → decision POST とのレースは解消
- `GenerateProgramDayPlansService` は元々冪等（existing チェック済み）
- **現時点で実害のあるバグは存在しない**。改善は設計品質とパフォーマンスの向上

## 設計方針

### 案 A: POST `/today/prepare` エンドポイント新設（推奨）

フロントエンドが Today 画面マウント時に `POST /today/prepare` を呼び、完了後に Inertia reload する。

```
[画面マウント]
  → POST /today/prepare { date }
    → GenerateProgramDayPlansService::handle()
    → EvaluateRulesForDayService::handle()
    → return { prepared: true }
  → router.reload({ only: ['plans', 'ops'] })
    → GET /today（読み取りのみ）
```

**メリット**:
- GET が純粋な読み取りになる
- 既存の `DailyCheckinController`・`SymptomObservationController` のパターンと一貫
- prepare の結果はフロントが reload で即反映するため UX 変化なし

**デメリット**:
- 初回表示が 2 リクエスト（prepare + GET）になる。ただし prepare は非同期で先行可能
- フロントの変更が必要

### 案 B: Inertia deferred props

```php
return Inertia::render('Today/Index', [
    'date' => $targetDate->toDateString(),
    'plans' => Inertia::defer(function () use (...) {
        $generateProgramDayPlans->handle($user, $targetDate);
        return RoutinePlanResource::collection($query->handle(...))->resolve();
    }),
    'ops' => Inertia::defer(function () use (...) {
        $evaluateRules->handle($user, $targetDate);
        return $opsQuery->handle($user, $targetDate);
    }),
]);
```

**メリット**:
- フロントの変更が最小（`usePage` で deferred props のローディング状態を表示するだけ）
- 画面の初期表示が速い（スケルトン → データ到着で描画）

**デメリット**:
- 依然として GET リクエスト内で書き込みが走る（HTTP 意味論的には改善なし）
- defer のコールバックは Inertia が別リクエストで呼ぶが、そのリクエストも GET

### 案 C: イベント駆動（checkin/symptom 保存時に準備完了）

`DailyCheckinController` と `SymptomObservationController` は既に `evaluateRules->handle()` を呼んでいる。`GenerateProgramDayPlansService` も同様にこれらの POST の後処理に追加し、GET は完全に読み取りのみにする。

```
PUT /today/checkin → evaluateRules + generatePlans (済)
POST /today/symptoms → evaluateRules + generatePlans (追加)
GET /today → 読み取りのみ
```

**メリット**:
- 新エンドポイント不要
- GET が完全に純粋

**デメリット**:
- 「チェックインも症状記録もせずに `/today` を初回表示」するケースでプランが未生成のまま表示される
- 初回アクセスのトリガーがないため、別途 prepare が必要（案 A に帰着）

## 推奨: 案 A（POST `/today/prepare`）

理由:
1. HTTP 意味論が正しくなる（GET = 読み取り、POST = 書き込み）
2. 既存パターン（`POST /today/program-choice`、`PUT /today/checkin`）と一貫
3. 将来の最適化余地が大きい（prepare を非同期化/キュー化しやすい）
4. 案 C の「初回未生成」問題を回避できる

## 実装ステップ（案 A 採用時）

### Phase 1: バックエンド

1. `TodayController` に `prepare` メソッドを追加:
   ```php
   public function prepare(ShowTodayRequest $request, ...): JsonResponse
   {
       $generateProgramDayPlans->handle($user, $targetDate);
       $evaluateRules->handle($user, $targetDate);
       return response()->json(['prepared' => true]);
   }
   ```

2. ルート追加: `Route::post('today/prepare', ...)->name('today.prepare')`

3. `index` メソッドから `$generateProgramDayPlans->handle()` と `$evaluateRules->handle()` を削除

### Phase 2: フロントエンド

4. `Today/Index.vue` の `onMounted` で `POST /today/prepare` を呼び、完了後に `router.reload({ only: ['plans', 'ops'] })`

5. 初期表示はスケルトン/ローディング状態を表示（plans・ops が空の間）

### Phase 3: テスト修正

6. 既存テストで `GET /today` の前に `POST /today/prepare` を呼ぶよう修正:
   - `TodayOpsPhaseTest::test_repeated_today_visits_do_not_grow_rule_evaluations`
   - `TodayOpsPhaseTest::test_repeated_rule_evaluation_preserves_recommendation_ids`
   - `TodayOpsPhaseTest::test_recommendation_decision_applies_approval_a_skip`
   - `TodayOpsPhaseTest::test_other_user_cannot_decide_recommendation`
   - `TodayOpsPhaseTest::test_today_default_date_uses_user_timezone_not_utc`

7. prepare エンドポイント自体のテスト追加（認証・日付バリデーション・冪等性）

## 移行の安全性

- N-02 修正により、仮に GET に副作用が残っていても recommendation ID は安定する
- `GenerateProgramDayPlansService` は元々冪等なので、prepare と GET で二重呼びしても安全
- 段階的移行: Phase 1 で prepare を追加しつつ index の呼び出しも残し、フロント移行完了後に index から削除

## 優先度と見積もり

- 優先度: **低〜中**（N-02 で実害は解消済み。設計品質の改善）
- 見積もり: バックエンド 0.5h / フロント 1h / テスト修正 1h
- 推奨タイミング: 次の Today 画面の機能追加（SM-D03〜D08）と合わせて実施
