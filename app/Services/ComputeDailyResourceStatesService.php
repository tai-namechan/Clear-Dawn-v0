<?php

namespace App\Services;

use App\Models\DailyCheckin;
use App\Models\DailyResourceState;
use App\Models\PersonalBaseline;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * チェックイン値から簡易 EWMA / readiness を日次計算する（決定論・同入力同出力）。
 * 較正期間（baseline sample_count < 28）は readiness を null のまま残す。
 */
class ComputeDailyResourceStatesService
{
    private const EWMA_ALPHA = 0.3;

    /** @var list<string> */
    private const RESOURCES = [
        'sleep_quality',
        'fatigue',
        'muscle_soreness',
        'stress',
        'mood',
        'readiness_self',
    ];

    /**
     * @return Collection<int, DailyResourceState>
     */
    public function handle(User $user, Carbon $date): Collection
    {
        $checkin = DailyCheckin::query()
            ->where('user_id', $user->id)
            ->whereDate('checked_on', $date->toDateString())
            ->first();

        if ($checkin === null) {
            return collect();
        }

        return DB::transaction(function () use ($user, $date, $checkin): Collection {
            $states = collect();

            foreach (self::RESOURCES as $resourceKey) {
                $value = $checkin->{$resourceKey};

                if ($value === null) {
                    continue;
                }

                $previous = DailyResourceState::query()
                    ->where('user_id', $user->id)
                    ->where('resource_key', $resourceKey)
                    ->where('state_on', '<', $date->toDateString())
                    ->orderByDesc('state_on')
                    ->first();

                $ewma = $previous?->ewma !== null
                    ? (self::EWMA_ALPHA * (float) $value) + ((1 - self::EWMA_ALPHA) * (float) $previous->ewma)
                    : (float) $value;

                $existingToday = DailyResourceState::query()
                    ->where('user_id', $user->id)
                    ->where('resource_key', $resourceKey)
                    ->whereDate('state_on', $date->toDateString())
                    ->exists();

                $baseline = PersonalBaseline::query()->firstOrNew([
                    'user_id' => $user->id,
                    'resource_key' => $resourceKey,
                ]);

                $previousCount = (int) ($baseline->sample_count ?? 0);
                $sampleCount = $existingToday ? max(1, $previousCount) : $previousCount + 1;

                $baseline->mean_value = $existingToday || $previousCount === 0
                    ? (float) $value
                    : ((((float) $baseline->mean_value) * $previousCount) + (float) $value) / $sampleCount;
                $baseline->sample_count = $sampleCount;
                $baseline->window_start = $baseline->window_start ?? $date->toDateString();
                $baseline->window_end = $date->toDateString();
                $baseline->computed_at = now();
                $baseline->save();

                $zLoad = null;
                $readiness = null;

                if ($sampleCount >= 28) {
                    $mean = (float) $baseline->mean_value;
                    $stddev = max(0.5, (float) ($baseline->stddev_value ?? 1.5));
                    $zLoad = ((float) $value - $mean) / $stddev;
                    $readiness = match ($resourceKey) {
                        'fatigue', 'muscle_soreness', 'stress' => max(0, min(10, 5 - $zLoad)),
                        default => max(0, min(10, 5 + $zLoad)),
                    };
                }

                $states->push(DailyResourceState::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'state_on' => $date->toDateString(),
                        'resource_key' => $resourceKey,
                    ],
                    [
                        'ewma' => round($ewma, 4),
                        'z_load' => $zLoad !== null ? round($zLoad, 4) : null,
                        'rel_strain' => $zLoad !== null ? round(abs($zLoad), 4) : null,
                        'readiness' => $readiness !== null ? round($readiness, 4) : null,
                        'inputs_snapshot' => [
                            'raw' => $value,
                            'sample_count' => $sampleCount,
                            'calibrating' => $sampleCount < 28,
                        ],
                    ],
                ));
            }

            return $states->values();
        });
    }
}
