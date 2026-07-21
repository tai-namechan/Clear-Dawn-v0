<?php

namespace App\Services;

use App\Enums\PlanGenerationSource;
use App\Enums\RecommendationScope;
use App\Enums\RecommendationStatus;
use App\Models\DailyCheckin;
use App\Models\PersonalBaseline;
use App\Models\PersonalProfileEntry;
use App\Models\Recommendation;
use App\Models\RoutinePlan;
use App\Models\RuleDefinition;
use App\Models\RuleEvaluation;
use App\Models\SymptomObservation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 決定論ルール評価 → recommendations（作戦カード）生成。同入力同出力。
 * 推奨カードは冪等に再利用し、ID を安定させる。
 */
class EvaluateRulesForDayService
{
    public function __construct(
        private readonly EnsureDefaultRuleDefinitionsService $ensureDefaultRuleDefinitions,
    ) {}

    /**
     * @return Collection<int, Recommendation>
     */
    public function handle(User $user, Carbon $date): Collection
    {
        $this->ensureDefaultRuleDefinitions->handle();

        return DB::transaction(function () use ($user, $date): Collection {
            $rules = RuleDefinition::query()
                ->where('is_active', true)
                ->where(function ($query) use ($user): void {
                    $query->whereNull('user_id')->orWhere('user_id', $user->id);
                })
                ->orderByDesc('is_hard_gate')
                ->get();

            $checkin = DailyCheckin::query()
                ->where('user_id', $user->id)
                ->whereDate('checked_on', $date->toDateString())
                ->first();

            $calibrating = PersonalBaseline::query()
                ->where('user_id', $user->id)
                ->where('sample_count', '<', 28)
                ->exists()
                || ! PersonalBaseline::query()->where('user_id', $user->id)->exists();

            $h7Locked = (bool) PersonalProfileEntry::currentFor($user, PersonalProfileEntry::KEY_H7_NEURAL_LOCK, $date)?->value_text
                || SymptomObservation::query()
                    ->where('user_id', $user->id)
                    ->where('symptom_kind', 'neural_ulnar')
                    ->where('observed_on', '>=', $date->copy()->subDays(7)->toDateString())
                    ->exists();

            $programPlans = RoutinePlan::query()
                ->where('user_id', $user->id)
                ->whereDate('scheduled_on', $date->toDateString())
                ->where('generation_source', PlanGenerationSource::Program->value)
                ->with(['dayTemplate', 'steps'])
                ->get();

            $recommendations = collect();
            $interruptUsed = false;
            $keptEvaluationIds = [];

            foreach ($rules as $rule) {
                $result = match ($rule->key) {
                    'missing_daily_checkin' => [
                        'triggered' => $checkin === null,
                        'inputs' => ['has_checkin' => $checkin !== null],
                    ],
                    'calibration_period' => [
                        'triggered' => $calibrating,
                        'inputs' => ['calibrating' => $calibrating],
                    ],
                    'h7_neural_symptom_lock' => [
                        'triggered' => $h7Locked,
                        'inputs' => ['h7_locked' => $h7Locked],
                    ],
                    'program_day_ready' => [
                        'triggered' => $programPlans->isNotEmpty(),
                        'inputs' => [
                            'plan_ids' => $programPlans->pluck('id')->all(),
                            'day_codes' => $programPlans->pluck('dayTemplate.code')->filter()->values()->all(),
                        ],
                    ],
                    default => ['triggered' => false, 'inputs' => []],
                };

                $evaluation = RuleEvaluation::query()
                    ->where('user_id', $user->id)
                    ->where('rule_definition_id', $rule->id)
                    ->whereDate('evaluated_on', $date->toDateString())
                    ->first();

                if ($evaluation === null) {
                    $evaluation = new RuleEvaluation([
                        'user_id' => $user->id,
                        'rule_definition_id' => $rule->id,
                        'evaluated_on' => $date->toDateString(),
                    ]);
                }

                $evaluation->fill([
                    'triggered' => $result['triggered'],
                    'inputs_snapshot' => $result['inputs'],
                    'outputs_snapshot' => ['triggered' => $result['triggered']],
                ]);
                $evaluation->save();

                if (! $result['triggered']) {
                    continue;
                }

                if ($calibrating && $rule->key === 'missing_daily_checkin') {
                    // 入力促しは較正中でも出す
                } elseif ($calibrating && ! $rule->is_hard_gate && $rule->key !== 'calibration_period' && $rule->key !== 'program_day_ready') {
                    continue;
                }

                $isInterrupt = $rule->is_hard_gate && ! $interruptUsed;

                if ($isInterrupt) {
                    $interruptUsed = true;
                }

                $existing = Recommendation::query()
                    ->where('user_id', $user->id)
                    ->where('rule_evaluation_id', $evaluation->id)
                    ->whereDate('recommended_on', $date->toDateString())
                    ->where('status', RecommendationStatus::Pending)
                    ->whereDoesntHave('decision')
                    ->first();

                if ($existing !== null) {
                    $keptEvaluationIds[] = $evaluation->id;
                    $recommendations->push($existing->load('options'));

                    continue;
                }

                $recommendation = $this->createRecommendation(
                    $user,
                    $date,
                    $rule,
                    $evaluation,
                    $isInterrupt,
                    $programPlans,
                    $calibrating,
                );

                $keptEvaluationIds[] = $evaluation->id;
                $recommendations->push($recommendation);
            }

            // ルールが発火しなくなった stale な pending 推奨を削除
            Recommendation::query()
                ->where('user_id', $user->id)
                ->whereDate('recommended_on', $date->toDateString())
                ->where('status', RecommendationStatus::Pending)
                ->whereDoesntHave('decision')
                ->when($keptEvaluationIds !== [], fn ($query) => $query->whereNotIn('rule_evaluation_id', $keptEvaluationIds))
                ->delete();

            return $recommendations->values();
        });
    }

    /**
     * @param  Collection<int, RoutinePlan>  $programPlans
     */
    private function createRecommendation(
        User $user,
        Carbon $date,
        RuleDefinition $rule,
        RuleEvaluation $evaluation,
        bool $isInterrupt,
        Collection $programPlans,
        bool $calibrating,
    ): Recommendation {
        $payload = match ($rule->key) {
            'missing_daily_checkin' => [
                'title' => '30秒チェックインを記録',
                'rationale' => '今日のコンディション入力がまだありません。作戦の前提になります。',
                'goal_impact' => '入力後に readiness と作戦カードが更新されます。',
                'options' => [
                    ['action_key' => 'detail', 'label' => 'チェックインする', 'description' => '睡眠・疲労・張りを30秒で記録'],
                ],
            ],
            'calibration_period' => [
                'title' => '較正期間中',
                'rationale' => 'ベースライン蓄積が4週未満のため、警告系アラートは抑制しています。',
                'goal_impact' => '通常どおりプログラムを実行し、データを貯めてください。',
                'options' => [
                    ['action_key' => 'execute', 'label' => '了解', 'description' => null],
                ],
            ],
            'h7_neural_symptom_lock' => [
                'title' => 'H7 神経症状ロック',
                'rationale' => '尺骨神経症状またはロック設定のため、投球負荷の開始前に受診評価が必要です。',
                'goal_impact' => '投球 DAY は見送りまたはアームケア中心へ。',
                'options' => [
                    ['action_key' => 'skip', 'label' => '投球を見送る', 'description' => '今日のプログラムプランをアーカイブ'],
                    ['action_key' => 'adjust', 'label' => '調整する', 'description' => '今日だけプランを編集可能にする'],
                    ['action_key' => 'detail', 'label' => '詳細', 'description' => '症状記録へ'],
                ],
            ],
            'program_day_ready' => [
                'title' => $programPlans->first()?->title ?? '今日のプログラム',
                'rationale' => 'アクティブなプログラム版から今日の DAY を生成済みです。',
                'goal_impact' => '実行・軽くやる・見送りを選べます（承認A）。',
                'options' => [
                    ['action_key' => 'execute', 'label' => '実行', 'description' => 'プランを Ready のまま開始'],
                    ['action_key' => 'lighten', 'label' => '軽くやる', 'description' => 'skippable STEP を除外'],
                    ['action_key' => 'adjust', 'label' => '調整', 'description' => '今日だけ Draft で編集'],
                    ['action_key' => 'skip', 'label' => '見送り', 'description' => '今日のプランをアーカイブ'],
                ],
            ],
            default => [
                'title' => $rule->title,
                'rationale' => $rule->description,
                'goal_impact' => null,
                'options' => [
                    ['action_key' => 'detail', 'label' => '詳細', 'description' => null],
                ],
            ],
        };

        /** @var Recommendation $recommendation */
        $recommendation = Recommendation::query()->create([
            'user_id' => $user->id,
            'rule_evaluation_id' => $evaluation->id,
            'recommended_on' => $date->toDateString(),
            'scope' => RecommendationScope::A,
            'title' => $payload['title'],
            'rationale' => $calibrating && $rule->key !== 'calibration_period'
                ? ($payload['rationale'].'（較正中）')
                : $payload['rationale'],
            'goal_impact' => $payload['goal_impact'],
            'plan_diff' => [
                'plan_ids' => $programPlans->pluck('id')->all(),
            ],
            'confidence' => $rule->confidence,
            'missing_data' => $rule->key === 'missing_daily_checkin' ? ['daily_checkin'] : null,
            'is_interrupt' => $isInterrupt,
            'status' => RecommendationStatus::Pending,
        ]);

        foreach ($payload['options'] as $index => $option) {
            $recommendation->options()->create([
                'action_key' => $option['action_key'],
                'label' => $option['label'],
                'description' => $option['description'],
                'sort_order' => $index,
            ]);
        }

        return $recommendation->load('options');
    }
}
