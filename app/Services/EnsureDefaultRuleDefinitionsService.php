<?php

namespace App\Services;

use App\Enums\RuleDefinitionKind;
use App\Models\RuleDefinition;

/**
 * グローバル（user_id null）の初期ルール定義を冪等に用意する。
 */
class EnsureDefaultRuleDefinitionsService
{
    public function handle(): void
    {
        $definitions = [
            [
                'key' => 'missing_daily_checkin',
                'kind' => RuleDefinitionKind::UserPolicy,
                'title' => '30秒チェックイン未入力',
                'description' => '当日のチェックインが無いとき、作戦カードで入力を促す。',
                'params' => ['priority' => 10],
                'is_hard_gate' => false,
                'confidence' => 0.9,
            ],
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
                'title' => '較正期間中',
                'description' => 'ベースライン sample_count が 28 未満のときは警告系カードを出さず「較正中」と表示する。',
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
    }
}
