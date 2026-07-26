<?php

namespace App\Support;

final class TeamSafeBands
{
    public function intensityLabel(?float $sessionRpe): string
    {
        if ($sessionRpe === null) {
            return '未記録';
        }

        return match (true) {
            $sessionRpe <= 3.0 => '軽め',
            $sessionRpe <= 6.0 => 'ふつう',
            $sessionRpe <= 8.0 => '強め',
            default => 'かなり強め',
        };
    }

    public function recoveryLabel(?int $readinessSelf, ?int $fatigue): string
    {
        $score = $readinessSelf;
        if ($score === null && $fatigue !== null) {
            $score = max(0, 10 - $fatigue);
        }

        if ($score === null) {
            return '記録なし';
        }

        return match (true) {
            $score >= 7 => '回復寄り',
            $score >= 4 => 'ふつう',
            default => '疲労寄り',
        };
    }

    public function trainingAvailabilitySummary(?int $readinessSelf, ?int $fatigue): string
    {
        $label = $this->recoveryLabel($readinessSelf, $fatigue);

        return match ($label) {
            '回復寄り' => '記録上、通常確認の対象',
            'ふつう' => '記録上、様子を見る対象',
            '疲労寄り' => '記録上、本人への確認を推奨',
            default => 'コンディション記録なし',
        };
    }

    public function goalProgressPercent(string $status): ?int
    {
        return match ($status) {
            'achieved' => 100,
            'abandoned' => null,
            'draft' => null,
            default => null,
        };
    }

    public function goalStatusLabel(string $status): string
    {
        return match ($status) {
            'achieved' => '達成',
            'abandoned' => '中止',
            'draft' => '下書き',
            'active' => '進行中',
            default => '不明',
        };
    }
}
