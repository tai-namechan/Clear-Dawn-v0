<?php

namespace App\Services;

use App\Enums\RuleDefinitionKind;
use App\Models\RuleDefinition;

/**
 * グローバル（user_id null）の初期ルール定義を冪等に用意する。
 */
class EnsureDefaultRuleDefinitionsService
{
    /**
     * Rules that are no longer offered; existing rows are switched off.
     *
     * @var list<string>
     */
    private const RETIRED_KEYS = ['missing_daily_checkin'];

    public function handle(): void
    {
        $definitions = [
            [
                'key' => 'h7_neural_symptom_lock',
                'kind' => RuleDefinitionKind::ClinicianRule,
                'title' => 'H7 尺骨神経症状ロック',
                'description' => 'neural_ulnar 症状またはプロファイルの H7 ロックがあるとき、投球 DAY を割り込み提示する。',
                'params' => ['symptom_kind' => 'neural_ulnar', 'cooldown_hours' => 48],
                'is_hard_gate' => true,
                'confidence' => 0.95,
                'evidence' => 'BODY MONITOR H7',
            ],
            [
                'key' => 'calibration_period',
                'kind' => RuleDefinitionKind::EvidenceRule,
                'title' => '個人の基準づくりの期間です',
                'description' => 'ベースライン sample_count が 28 未満のときは警告系カードを出さず、個人の基準がまだ固まっていない旨を表示する。',
                'params' => ['min_samples' => 28],
                'is_hard_gate' => false,
                'confidence' => 0.8,
            ],
            [
                'key' => 'program_day_ready',
                'kind' => RuleDefinitionKind::ProgramRule,
                'title' => '今日のプログラム DAY',
                'description' => '生成済みのプログラム DAY プランを作戦カードとして提示する。',
                'params' => [],
                'is_hard_gate' => false,
                'confidence' => 0.85,
            ],
        ];

        foreach ($definitions as $definition) {
            RuleDefinition::query()->firstOrCreate(
                [
                    'user_id' => null,
                    'key' => $definition['key'],
                    'version_number' => 1,
                ],
                [
                    'kind' => $definition['kind'],
                    'title' => $definition['title'],
                    'description' => $definition['description'],
                    'params' => $definition['params'],
                    'evidence' => $definition['evidence'] ?? null,
                    'confidence' => $definition['confidence'],
                    'is_active' => true,
                    'is_hard_gate' => $definition['is_hard_gate'],
                ],
            );
        }

        // 30秒チェックインは 2026-11-22 に廃止（docs/product/screens/today-ops.md）。
        RuleDefinition::query()
            ->whereNull('user_id')
            ->whereIn('key', self::RETIRED_KEYS)
            ->update(['is_active' => false]);
    }
}
