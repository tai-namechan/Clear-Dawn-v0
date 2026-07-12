<?php

namespace App\Domain\Yoyu\Services;

use App\Domain\Yoyu\Data\MarginAnalysis;
use App\Domain\Yoyu\Support\YoyuTravelConstants;

/**
 * Deterministic margin meter. No AI. Overlapping busy time must already be merged upstream.
 */
final class MarginAnalyzer
{
    public function analyze(int $busyMinutes, int $taskEstimateSum): MarginAnalysis
    {
        $working = YoyuTravelConstants::WORKING_MINUTES;
        $taskMinutes = min(max(0, $taskEstimateSum), YoyuTravelConstants::TASK_MINUTES_CAP);
        $busy = max(0, $busyMinutes);
        $load = min($working, $busy + $taskMinutes);
        $ratio = max(0.0, min(1.0, 1.0 - ($load / $working)));
        $score = (int) round($ratio * 100);

        $label = match (true) {
            $score > 50 => 'ゆったり',
            $score >= 20 => 'ちょうどいい',
            default => '詰まり気味',
        };

        return new MarginAnalysis(
            busyMinutes: $busy,
            taskMinutes: $taskMinutes,
            workingMinutes: $working,
            loadMinutes: $load,
            marginRatio: $ratio,
            marginScore: $score,
            marginLabel: $label,
        );
    }
}
